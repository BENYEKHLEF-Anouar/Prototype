<?php
require '../config/config.php';
require '../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Check if admin is accessing
$is_admin = isset($_SESSION['admin']) && $_SESSION['admin']['role'] === 'admin' && isset($_GET['admin']);

// --- DATA FETCHING (No changes needed) ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $redirect_url = $is_admin ? "admin_dashboard.php" : "sessions.php";
    header("Location: $redirect_url");
    exit();
}
$sessionId = (int)$_GET['id'];
$stmt = $pdo->prepare("
    SELECT s.*, m.idMentor, u.prenomUtilisateur AS mentor_prenom, u.nomUtilisateur AS mentor_nom, u.photoUrl AS mentor_photo
    FROM Session s
    JOIN Mentor m ON s.idMentorAnimateur = m.idMentor
    JOIN Utilisateur u ON m.idUtilisateur = u.idUtilisateur
    WHERE s.idSession = :session_id");
$stmt->execute([':session_id' => $sessionId]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    $redirect_url = $is_admin ? "admin_dashboard.php" : "sessions.php";
    header("Location: $redirect_url");
    exit();
}

// --- HELPER FUNCTIONS ---
function formatDuration($m) { return ($m < 60) ? $m . 'min' : floor($m / 60) . 'h' . str_pad($m % 60, 2, '0', STR_PAD_LEFT); }

// This function generates a CSS class based on the session subject
function getSessionStyleClass($subject) {
    if (empty($subject)) {
        return 'session-card--default';
    }
    // Clean up the subject name to create a CSS-friendly slug
    $subject = str_replace(['é', 'è', 'ê', 'à', 'ç', 'ô', 'î', 'û', ' ', '/'], ['e', 'e', 'e', 'a', 'c', 'o', 'i', 'u', '-', '-'], $subject);
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '', $subject));
    return 'session-card--' . trim($slug, '-');
}

require_once '../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/profile.css?v=<?php echo time(); ?>">

<main class="profile-page-main">
    <div class="container">
        <div class="profile-container">
            <!-- Main Content (Left Column) -->
            <div class="profile-main-content">
                <div class="session-page-header <?= getSessionStyleClass($session['sujet']) ?>">
                    <div class="session-header-icon"></div>
                    <span class="tag"><?= htmlspecialchars($session['sujet']) ?></span>
                    <h1><?= htmlspecialchars($session['titreSession']) ?></h1>
                    <p class="lead">Rejoignez cette session pour approfondir vos connaissances et poser vos questions à un expert.</p>
                </div>

                <div class="content-card">
                    <h2>Détails de la session</h2>
                    <div class="session-details-grid">
                        <div class="detail-item"><strong>Date:</strong> <span><?= date('l j F Y', strtotime($session['dateSession'])) ?></span></div>
                        <div class="detail-item"><strong>Heure:</strong> <span><?= date('H:i', strtotime($session['heureSession'])) ?></span></div>
                        <div class="detail-item"><strong>Durée:</strong> <span><?= formatDuration($session['duree_minutes']) ?></span></div>
                        <div class="detail-item"><strong>Niveau:</strong> <span><?= htmlspecialchars($session['niveau'] ?? 'Non spécifié') ?></span></div>
                        <div class="detail-item"><strong>Format:</strong> <span><?= $session['typeSession'] === 'en_ligne' ? 'En ligne' : 'Présentiel' ?></span></div>
                        <div class="detail-item"><strong>Tarif:</strong> <span><?= ($session['tarifSession'] > 0) ? number_format($session['tarifSession'], 2) . ' €' : 'Gratuit' ?></span></div>
                    </div>
                </div>
            </div>

            <!-- Sidebar (Right Column) -->
            <aside class="profile-sidebar">
                <?php if ($is_admin): ?>
                <div class="sidebar-card">
                    <h2>Actions Administrateur</h2>
                    <a href="admin_dashboard.php" class="btn btn-secondary" style="margin-bottom: 0.5rem;">
                        <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                    </a>
                    <button class="btn btn-danger" onclick="deleteSession(<?= $session['idSession'] ?>)" style="width: 100%;">
                        <i class="fas fa-trash"></i> Supprimer la session
                    </button>
                </div>
                <?php else: ?>
                <div class="sidebar-card">
                     <a href="register_for_session.php?id=<?= $session['idSession'] ?>" class="btn btn-primary"><i class="fas fa-check"></i>  Réserver ma place</a>
                </div>
                <?php endif; ?>
                 <div class="sidebar-card">
                    <h2>Animé par</h2>
                    <a href="mentor_profile.php?id=<?= $session['idMentor'] ?>" class="session-mentor-card">
                        <img src="<?= get_profile_image_path($session['mentor_photo']) ?>" alt="Photo de <?= htmlspecialchars($session['mentor_prenom']) ?>">
                        <div>
                            <h3><?= htmlspecialchars($session['mentor_prenom'] . ' ' . $session['mentor_nom']) ?></h3>
                        </div>
                    </a>
                </div>
            </aside>
        </div>
    </div>
</main>

<?php if ($is_admin): ?>
<script>
function deleteSession(sessionId) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette session ? Cette action est irréversible.')) return;

    fetch('actions/admin_delete_session.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `session_id=${sessionId}&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            window.location.href = 'admin_dashboard.php';
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        alert('Erreur de connexion');
    });
}
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>