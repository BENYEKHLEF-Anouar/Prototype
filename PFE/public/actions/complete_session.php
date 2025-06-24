<?php
require '../../config/config.php';
require '../../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

function send_json_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
}

function send_json_success($message, $data = null) {
    $response = ['status' => 'success', 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Security checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_error('Méthode non autorisée.', 405);
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'mentor') {
    send_json_error('Accès refusé.', 403);
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    send_json_error('Erreur de validation CSRF.', 403);
}

// Validate input
$sessionId = filter_input(INPUT_POST, 'sessionId', FILTER_VALIDATE_INT);
if (!$sessionId) {
    send_json_error('ID de session invalide.');
}

$userId = $_SESSION['user']['id'];

try {
    // Get mentor ID
    $stmt = $pdo->prepare("SELECT idMentor FROM Mentor WHERE idUtilisateur = ?");
    $stmt->execute([$userId]);
    $mentor = $stmt->fetch();
    
    if (!$mentor) {
        send_json_error('Profil mentor non trouvé.', 404);
    }
    
    $mentorId = $mentor['idMentor'];
    
    // Check if session exists, belongs to this mentor, and can be completed
    $stmt = $pdo->prepare("
        SELECT idSession, titreSession, statutSession, dateSession, heureSession,
               (SELECT COUNT(*) FROM Participation WHERE idSession = ? AND statutParticipation = 'validee') as confirmedParticipants
        FROM Session 
        WHERE idSession = ? AND idMentorAnimateur = ?
    ");
    $stmt->execute([$sessionId, $sessionId, $mentorId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        send_json_error('Session non trouvée ou vous n\'êtes pas autorisé à la modifier.', 404);
    }
    
    // Check if session can be completed
    if ($session['statutSession'] !== 'validee') {
        send_json_error('Seules les sessions validées peuvent être marquées comme terminées.');
    }
    
    // Check if session date/time has passed (optional - you might want to allow early completion)
    $sessionDateTime = new DateTime($session['dateSession'] . ' ' . $session['heureSession']);
    $now = new DateTime();
    
    if ($sessionDateTime > $now) {
        // Allow completion up to 15 minutes before scheduled time
        $sessionDateTime->sub(new DateInterval('PT15M'));
        if ($sessionDateTime > $now) {
            send_json_error('Cette session ne peut être marquée comme terminée qu\'après son heure de début.');
        }
    }
    
    // Update session status to 'terminee'
    $stmt = $pdo->prepare("UPDATE Session SET statutSession = 'terminee' WHERE idSession = ? AND idMentorAnimateur = ?");
    $success = $stmt->execute([$sessionId, $mentorId]);
    
    if ($success && $stmt->rowCount() > 0) {
        // Create notification for students to evaluate the session
        if ($session['confirmedParticipants'] > 0) {
            // Get student IDs who participated
            $stmt = $pdo->prepare("
                SELECT DISTINCT e.idUtilisateur 
                FROM Participation p 
                JOIN Etudiant e ON p.idEtudiant = e.idEtudiant 
                WHERE p.idSession = ?
            ");
            $stmt->execute([$sessionId]);
            $studentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Send notification to each student
            foreach ($studentIds as $studentId) {
                $stmt = $pdo->prepare("
                    INSERT INTO Notification (idUtilisateur, typeNotification, contenuNotification, estLue) 
                    VALUES (?, 'session_completed', ?, 0)
                ");
                $notificationContent = "La session \"{$session['titreSession']}\" est terminée. Vous pouvez maintenant l'évaluer.";
                $stmt->execute([$studentId, $notificationContent]);
            }
        }
        
        send_json_success('Session marquée comme terminée avec succès.', [
            'sessionId' => $sessionId,
            'sessionTitle' => $session['titreSession'],
            'newStatus' => 'terminee'
        ]);
    } else {
        send_json_error('Erreur lors de la mise à jour du statut de la session.');
    }

} catch (PDOException $e) {
    error_log("Complete session error: " . $e->getMessage());
    send_json_error('Erreur de base de données.', 500);
} catch (Exception $e) {
    error_log("Complete session error: " . $e->getMessage());
    send_json_error('Erreur lors de la completion.', 500);
}
?>
