<?php
require '../../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Checks for AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Méthode non autorisée.']);
    exit;
}

if (!isset($_SESSION['admin']) || $_SESSION['admin']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accès refusé.']);
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Erreur CSRF.']);
    exit;
}

$session_id = intval($_POST['session_id'] ?? 0);

if ($session_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID session invalide.']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get session info for logging
    $stmt = $pdo->prepare("SELECT titreSession FROM Session WHERE idSession = ?");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo json_encode(['status' => 'error', 'message' => 'Session non trouvée.']);
        exit;
    }
    
    // Delete related records first
    $stmt = $pdo->prepare("DELETE FROM Participation WHERE idSession = ?");
    $stmt->execute([$session_id]);
    
    // Delete the session
    $stmt = $pdo->prepare("DELETE FROM Session WHERE idSession = ?");
    $stmt->execute([$session_id]);
    
    $pdo->commit();
    
    // Log the action
    log_error("Admin {$_SESSION['admin']['email']} deleted session: {$session['titreSession']} (ID: $session_id)");
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Session supprimée avec succès.'
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    log_error("Admin delete session error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur lors de la suppression de la session.'
    ]);
}
?>
