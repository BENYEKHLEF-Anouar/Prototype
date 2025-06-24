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

$user_id = intval($_POST['user_id'] ?? 0);

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID utilisateur invalide.']);
    exit;
}

try {
    // Verify the user
    $stmt = $pdo->prepare("UPDATE Utilisateur SET verified = 1 WHERE idUtilisateur = ?");
    $stmt->execute([$user_id]);
    
    if ($stmt->rowCount() > 0) {
        // Log the action
        log_error("Admin {$_SESSION['admin']['email']} verified user ID: $user_id");
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Utilisateur vérifié avec succès.'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Utilisateur non trouvé ou déjà vérifié.'
        ]);
    }
} catch (PDOException $e) {
    log_error("Admin verify user error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur lors de la vérification de l\'utilisateur.'
    ]);
}
?>
