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
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'mentor') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Accès refusé.']);
    exit;
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Erreur CSRF.']);
    exit;
}

$idUtilisateur = $_SESSION['user']['id'];
$idSession = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

if (!$idSession || !in_array($action, ['accept', 'decline'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Données invalides.']);
    exit;
}

try {
    // Get mentor_id from user_id
    $stmt_mentor = $pdo->prepare("SELECT idMentor FROM Mentor WHERE idUtilisateur = ?");
    $stmt_mentor->execute([$idUtilisateur]);
    $mentor = $stmt_mentor->fetch();

    if (!$mentor) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Profil mentor non trouvé.']);
        exit;
    }
    $idMentor = $mentor['idMentor'];

    // Verify the mentor owns this session
    $stmt_verify = $pdo->prepare("SELECT idSession FROM Session WHERE idSession = ? AND idMentorAnimateur = ? AND statutSession = 'en_attente'");
    $stmt_verify->execute([$idSession, $idMentor]);

    if ($stmt_verify->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Session non trouvée ou déjà traitée.']);
        exit;
    }

    // Update the session status
    $newStatus = ($action === 'accept') ? 'validee' : 'annulee';
    $stmt_update = $pdo->prepare("UPDATE Session SET statutSession = ? WHERE idSession = ?");
    $stmt_update->execute([$newStatus, $idSession]);

    // Send notification to the student
    $stmt_session = $pdo->prepare("SELECT s.titreSession, s.idEtudiantDemandeur, e.idUtilisateur as studentUserId FROM Session s JOIN Etudiant e ON s.idEtudiantDemandeur = e.idEtudiant WHERE s.idSession = ?");
    $stmt_session->execute([$idSession]);
    $sessionInfo = $stmt_session->fetch();

    if ($sessionInfo) {
        $message = $action === 'accept'
            ? "Votre demande pour la session '{$sessionInfo['titreSession']}' a été acceptée ! Vous pouvez maintenant accéder au lien de réunion."
            : "Votre demande pour la session '{$sessionInfo['titreSession']}' a été déclinée.";

        $stmt_message = $pdo->prepare("INSERT INTO Message (idExpediteur, idDestinataire, contenuMessage) VALUES (?, ?, ?)");
        $stmt_message->execute([$idUtilisateur, $sessionInfo['studentUserId'], $message]);
    }
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Session ' . ($newStatus === 'validee' ? 'validée' : 'annulée') . '.']);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Handle session request error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erreur de base de données.']);
}
exit;