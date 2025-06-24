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
    $pdo->beginTransaction();
    
    // Get user info for logging
    $stmt = $pdo->prepare("SELECT emailUtilisateur, role FROM Utilisateur WHERE idUtilisateur = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Utilisateur non trouvé.']);
        exit;
    }
    
    // Delete related records first
    if ($user['role'] === 'mentor') {
        // Delete mentor-specific records
        $stmt = $pdo->prepare("DELETE FROM Session WHERE idMentorAnimateur IN (SELECT idMentor FROM Mentor WHERE idUtilisateur = ?)");
        $stmt->execute([$user_id]);
        
        $stmt = $pdo->prepare("DELETE FROM Ressource WHERE idUtilisateur = ?");
        $stmt->execute([$user_id]);
        
        $stmt = $pdo->prepare("DELETE FROM Mentor WHERE idUtilisateur = ?");
        $stmt->execute([$user_id]);
    } elseif ($user['role'] === 'etudiant') {
        // Delete student-specific records
        $stmt = $pdo->prepare("DELETE FROM Participation WHERE idEtudiant IN (SELECT idEtudiant FROM Etudiant WHERE idUtilisateur = ?)");
        $stmt->execute([$user_id]);
        
        $stmt = $pdo->prepare("DELETE FROM Session WHERE idEtudiantDemandeur = ?");
        $stmt->execute([$user_id]);
        
        $stmt = $pdo->prepare("DELETE FROM Etudiant WHERE idUtilisateur = ?");
        $stmt->execute([$user_id]);
    }
    
    // Delete messages
    $stmt = $pdo->prepare("DELETE FROM Message WHERE idExpediteur = ? OR idDestinataire = ?");
    $stmt->execute([$user_id, $user_id]);
    
    // Delete notifications
    $stmt = $pdo->prepare("DELETE FROM Notification WHERE idUtilisateur = ?");
    $stmt->execute([$user_id]);
    
    // Delete badge attributions
    $stmt = $pdo->prepare("DELETE FROM Attribution WHERE idUtilisateur = ?");
    $stmt->execute([$user_id]);
    
    // Finally delete the user
    $stmt = $pdo->prepare("DELETE FROM Utilisateur WHERE idUtilisateur = ?");
    $stmt->execute([$user_id]);
    
    $pdo->commit();
    
    // Log the action
    log_error("Admin {$_SESSION['admin']['email']} deleted user: {$user['emailUtilisateur']} (ID: $user_id)");
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Utilisateur supprimé avec succès.'
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    log_error("Admin delete user error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur lors de la suppression de l\'utilisateur.'
    ]);
}
?>
