<?php
require '../config/config.php';
require '../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- 1. AUTHENTICATION & SECURITY ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'etudiant') {
    header('Location: login.php');
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$student_user_id = $_SESSION['user']['id'];

// --- 2. DATA FETCHING ---
try {
    // Basic Student Info for Sidebar
    $stmt_student_info = $pdo->prepare("SELECT u.nomUtilisateur, u.prenomUtilisateur, u.photoUrl, e.idEtudiant, e.niveau FROM Utilisateur u JOIN Etudiant e ON u.idUtilisateur = e.idUtilisateur WHERE u.idUtilisateur = ?");
    $stmt_student_info->execute([$student_user_id]);
    $student_info = $stmt_student_info->fetch(PDO::FETCH_ASSOC);
    if (!$student_info) { die("Erreur: Profil étudiant non trouvé."); }
    $student_id = $student_info['idEtudiant'];

    // Sidebar Stats
    $stmt_stats = $pdo->prepare("
        SELECT
        (SELECT COUNT(*) FROM Participation WHERE idEtudiant = ? AND idSession IN (SELECT idSession FROM Session WHERE statutSession = 'terminee')) as sessions_done_count,
        (SELECT COUNT(*) FROM Session WHERE idEtudiantDemandeur = ? AND statutSession = 'en_attente') as sessions_pending_count,
        (SELECT COUNT(DISTINCT idMentorAnimateur) FROM Session WHERE idEtudiantDemandeur = ?) as mentors_contacted_count,
        (SELECT COUNT(DISTINCT sujet) FROM Session WHERE idEtudiantDemandeur = ? AND statutSession = 'terminee') as subjects_studied_count
    ");
    $stmt_stats->execute([$student_id, $student_id, $student_id, $student_id]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

    // Badges
    $badge_icons = [
        'Débutant' => 'fa-seedling',
        'Mentor engagé' => 'fa-rocket',
        'Assidu' => 'fa-calendar-check',
        'Orateur' => 'fa-microphone-alt',
        'Expert' => 'fa-medal',
        'Premier Message' => 'fa-envelope',
        'Communicateur' => 'fa-comments'
    ];
    $stmt_badges = $pdo->prepare("SELECT b.nomBadge, b.descriptionBadge FROM Badge b JOIN Attribution a ON b.idBadge = a.idBadge WHERE a.idUtilisateur = ? LIMIT 3");
    $stmt_badges->execute([$student_user_id]);
    $badges = $stmt_badges->fetchAll(PDO::FETCH_ASSOC);

    // Main Dashboard: Next Session & Recent Messages
    $stmt_next_session = $pdo->prepare("SELECT s.titreSession, s.dateSession, s.heureSession, u.prenomUtilisateur as mentorPrenom, u.nomUtilisateur as mentorNom FROM Session s JOIN Mentor m ON s.idMentorAnimateur = m.idMentor JOIN Utilisateur u ON m.idUtilisateur = u.idUtilisateur WHERE s.idEtudiantDemandeur = ? AND s.statutSession = 'validee' AND CONCAT(s.dateSession, ' ', s.heureSession) >= NOW() ORDER BY s.dateSession, s.heureSession LIMIT 1");
    $stmt_next_session->execute([$student_id]);
    $next_session = $stmt_next_session->fetch(PDO::FETCH_ASSOC);

    $stmt_messages = $pdo->prepare("SELECT m.contenuMessage, u.prenomUtilisateur, u.nomUtilisateur FROM Message m JOIN Utilisateur u ON m.idExpediteur = u.idUtilisateur WHERE m.idDestinataire = ? ORDER BY m.dateEnvoi DESC LIMIT 2");
    $stmt_messages->execute([$student_user_id]);
    $recent_messages = $stmt_messages->fetchAll(PDO::FETCH_ASSOC);

    // "Mes Sessions" Tab: Upcoming and Past
    $stmt_upcoming = $pdo->prepare("SELECT s.idSession, s.titreSession, s.dateSession, s.heureSession, s.statutSession, s.typeSession, s.lienReunion, u.prenomUtilisateur as p, u.nomUtilisateur as n, u.photoUrl as pic FROM Session s JOIN Mentor m ON s.idMentorAnimateur=m.idMentor JOIN Utilisateur u ON m.idUtilisateur=u.idUtilisateur WHERE s.idEtudiantDemandeur=? AND s.statutSession IN ('validee','en_attente') AND CONCAT(s.dateSession, ' ', s.heureSession) >= NOW() ORDER BY s.dateSession, s.heureSession");
    $stmt_upcoming->execute([$student_id]);
    $upcoming_sessions = $stmt_upcoming->fetchAll(PDO::FETCH_ASSOC);

    $stmt_past = $pdo->prepare("SELECT s.idSession, s.titreSession, s.dateSession, s.typeSession, s.lienReunion, u.prenomUtilisateur as p, u.nomUtilisateur as n, u.photoUrl as pic, p.notation FROM Session s JOIN Mentor m ON s.idMentorAnimateur=m.idMentor JOIN Utilisateur u ON m.idUtilisateur=u.idUtilisateur LEFT JOIN Participation p ON p.idSession = s.idSession AND p.idEtudiant = ? WHERE s.idEtudiantDemandeur=? AND (s.statutSession = 'terminee' OR (s.statutSession = 'validee' AND CONCAT(s.dateSession, ' ', s.heureSession) < NOW())) ORDER BY s.dateSession DESC");
    $stmt_past->execute([$student_id, $student_id]);
    $past_sessions = $stmt_past->fetchAll(PDO::FETCH_ASSOC);

    // "Mes Mentors" Tab
    $stmt_my_mentors = $pdo->prepare("SELECT DISTINCT u.idUtilisateur, u.prenomUtilisateur, u.nomUtilisateur, u.photoUrl, m.competences FROM Utilisateur u JOIN Mentor m ON u.idUtilisateur=m.idUtilisateur JOIN Session s ON s.idMentorAnimateur=m.idMentor WHERE s.idEtudiantDemandeur=?");
    $stmt_my_mentors->execute([$student_id]);
    $my_mentors = $stmt_my_mentors->fetchAll(PDO::FETCH_ASSOC);
    
    // "Messagerie" Tab
    $stmt_conversations = $pdo->prepare("SELECT m.contenuMessage, m.dateEnvoi, m.estLue, m.idExpediteur, u.idUtilisateur, u.prenomUtilisateur, u.nomUtilisateur, u.photoUrl FROM Message m JOIN (SELECT GREATEST(idExpediteur, idDestinataire) as u2, LEAST(idExpediteur, idDestinataire) as u1, MAX(idMessage) as max_id FROM Message WHERE ? IN (idExpediteur, idDestinataire) GROUP BY u1, u2) AS last_msg ON m.idMessage = last_msg.max_id JOIN Utilisateur u ON u.idUtilisateur = IF(m.idExpediteur = ?, m.idDestinataire, m.idExpediteur) ORDER BY m.dateEnvoi DESC");
    $stmt_conversations->execute([$student_user_id, $student_user_id]);
    $conversations_data = $stmt_conversations->fetchAll(PDO::FETCH_ASSOC);

    // Count evaluations to give
    $stmt_eval_todo = $pdo->prepare("SELECT COUNT(*) FROM Participation p JOIN Session s ON p.idSession = s.idSession WHERE p.idEtudiant = ? AND s.statutSession = 'terminee' AND p.notation IS NULL");
    $stmt_eval_todo->execute([$student_id]);
    $evaluations_to_give_count = $stmt_eval_todo->fetchColumn();

    // Count unread messages
    $stmt_unread = $pdo->prepare("SELECT COUNT(*) FROM Message WHERE idDestinataire = ? AND estLue = 0");
    $stmt_unread->execute([$student_user_id]);
    $unread_messages_count = $stmt_unread->fetchColumn();

    // Chart Data - Student's session participation over last 6 months
    $stmt_chart = $pdo->prepare("
        SELECT YEAR(s.dateSession) AS year, MONTH(s.dateSession) AS month, COUNT(p.idParticipation) as count
        FROM Participation p
        JOIN Session s ON p.idSession = s.idSession
        WHERE p.idEtudiant = ? AND s.statutSession = 'terminee' AND s.dateSession >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY year, month
        ORDER BY year, month ASC
    ");
    $stmt_chart->execute([$student_id]);
    $monthly_participation = $stmt_chart->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_KEY_PAIR);

    $chart_labels = [];
    $chart_values = [];
    $month_translations = ['January'=>'Janv','February'=>'Févr','March'=>'Mars','April'=>'Avr','May'=>'Mai','June'=>'Juin','July'=>'Juil','August'=>'Août','September'=>'Sept','October'=>'Oct','November'=>'Nov','December'=>'Déc'];

    for ($i = 5; $i >= 0; $i--) {
        $date = new DateTime("first day of -$i month");
        $chart_labels[] = $month_translations[$date->format('F')];
        $chart_values[] = $monthly_participation[$date->format('Y')][$date->format('n')][0] ?? 0;
    }

    // Add sample data if no data exists
    if (empty($chart_values) || array_sum($chart_values) == 0) {
        $chart_values = [1, 3, 2, 4, 3, 2]; // Sample data for testing
    }

    // --- Availability Data Fetching ---
    $stmt_availability = $pdo->prepare("SELECT jourSemaine, TIME_FORMAT(heureDebut, '%H:%i') as heureDebut FROM Disponibilite WHERE idUtilisateur = ?");
    $stmt_availability->execute([$student_user_id]);
    $availabilities_raw = $stmt_availability->fetchAll(PDO::FETCH_GROUP);
    $availability_map = [];
    foreach ($availabilities_raw as $day => $slots) {
        $availability_map[$day] = array_column($slots, 'heureDebut');
    }
    $days_of_week = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    $time_slots = ['09:00', '10:00', '11:00', '12:00', '14:00', '15:00', '16:00', '17:00'];

} catch (PDOException $e) {
    error_log("Student Dashboard Error: " . $e->getMessage());
    die("Une erreur est survenue. Veuillez réessayer plus tard.");
}

require '../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/student_dashboard.css?v=<?php echo time(); ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<main class="dashboard-container">
    <aside class="profile-sidebar">
        <a href="index.php" class="sidebar-back-link"><i class="fas fa-arrow-left"></i> Retour</a>
        <div class="profile-card">
            <div class="card-image-container"><img src="<?= get_profile_image_path($student_info['photoUrl']) ?>" alt="<?= sanitize($student_info['prenomUtilisateur']) ?>"></div>
            <div class="card-body">
                <h3 class="profile-name"><?= sanitize($student_info['prenomUtilisateur'] . ' ' . $student_info['nomUtilisateur']) ?></h3>
                <p class="profile-specialty"><?= sanitize($student_info['niveau']) ?></p>
                <div class="profile-rating"><i class="fa-solid fa-star"></i><strong><?= number_format(4.5, 1) ?></strong><span>(<?= $stats['sessions_done_count'] ?> sessions)</span></div>
                <div class="badge-showcase">
                    <h4>Mes Badges</h4>
                    <div class="badges-grid">
                        <?php if (empty($badges)): ?><p class="no-badges">Aucun badge.</p><?php else: foreach ($badges as $badge): ?>
                        <div class="badge" data-tooltip="<?= sanitize($badge['descriptionBadge']) ?>"><i class="fas <?= sanitize($badge_icons[$badge['nomBadge']] ?? 'fa-award') ?>"></i></div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-footer"><a href="edit_profile2.php" class="btn-edit-profile"><i class="fas fa-pencil-alt"></i> Modifier le profil</a></div>
        </div>
        <a href="sessions.php" class="btn-primary-full-width tab-link" data-tab="mes-sessions"><i class="fas fa-search"></i> Trouver des sessions</a>
    </aside>

    <div class="dashboard-main-content">
        <nav class="dashboard-nav">
            <ul>
                <li><a href="#statistiques" class="dashboard-tab active" data-tab="statistiques"><i class="fas fa-chart-line"></i> Statistiques</a></li>
                <li><a href="#mes-sessions" class="dashboard-tab" data-tab="mes-sessions"><i class="fas fa-tasks"></i> Mes Sessions <?php if($evaluations_to_give_count > 0): ?><span class="notification-badge"><?= $evaluations_to_give_count ?></span><?php endif; ?></a></li>
                <li><a href="#messagerie" class="dashboard-tab" data-tab="messagerie"><i class="fas fa-envelope"></i> Messagerie <?php if($unread_messages_count > 0): ?><span class="notification-badge"><?= $unread_messages_count ?></span><?php endif; ?></a></li>
                <li><a href="#disponibilites" class="dashboard-tab" data-tab="disponibilites"><i class="fas fa-calendar-alt"></i> Disponibilités</a></li>
            </ul>
        </nav>

        <div id="feedback-container-global" style="display: none; margin-bottom: 15px;"></div>

        <div id="statistiques" class="tab-content active">
            <h3 class="tab-title">Vos Statistiques</h3>
            <div class="stats-grid">
                <div class="stat-card"><i class="fas fa-graduation-cap stat-icon"></i><span class="stat-value"><?= $stats['sessions_done_count'] ?></span><p class="stat-label">Sessions suivies</p></div>
                <div class="stat-card"><i class="fas fa-clock stat-icon"></i><span class="stat-value"><?= $stats['sessions_pending_count'] ?></span><p class="stat-label">Sessions en attente</p></div>
                <div class="stat-card"><i class="fas fa-star stat-icon"></i><span class="stat-value"><?= number_format(4.5, 1) ?> / 5</span><p class="stat-label">Note moyenne donnée</p></div>
            </div>

            <div class="chart-container" style="margin-top: 2rem;">
                <div class="chart-card">
                    <h3 class="chart-title">Évolution de vos Sessions</h3>
                    <p class="chart-subtitle">Sessions terminées au cours des 6 derniers mois</p>
                    <div class="chart-wrapper">
                        <canvas id="studentChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid" style="margin-top: 2rem;">
                <div class="info-card">
                    <h3 class="card-title">Prochaine Session</h3>
                    <?php if ($next_session): ?>
                        <div class="session-info">
                            <p class="session-title-dash"><strong><?= sanitize($next_session['titreSession']) ?></strong></p>
                            <p class="session-mentor-dash">avec <?= sanitize($next_session['mentorPrenom'] . ' ' . substr($next_session['mentorNom'], 0, 1) . '.') ?></p>
                            <p class="session-time-dash"><i class="fas fa-calendar-alt"></i> <?= date_french('d M Y', strtotime($next_session['dateSession'])) ?> à <?= date('H:i', strtotime($next_session['heureSession'])) ?></p>
                        </div>
                        <a href="#mes-sessions" class="btn-primary-small tab-link" data-tab="mes-sessions">Voir mes sessions</a>
                    <?php else: ?>
                        <p class="no-data-text" style="padding:0; margin-bottom:1rem;">Aucune session à venir. C'est le moment d'en programmer une !</p>
                        <a href="sessions.php" class="btn-primary-small">Trouver des sessions</a>
                    <?php endif; ?>
                </div>
                <div class="info-card">
                    <h3 class="card-title">Messages Récents</h3>
                    <ul class="message-list">
                        <?php if (empty($recent_messages)): ?><p class="no-data-text" style="padding:0">Aucun message récent.</p><?php else: foreach ($recent_messages as $msg): ?>
                            <li>
                                <p class="message-author"><?= sanitize($msg['prenomUtilisateur'].' '.$msg['nomUtilisateur']) ?></p>
                                <p class="message-preview">"<?= sanitize(substr($msg['contenuMessage'], 0, 45)) ?>..."</p>
                                <a href="#messagerie" class="tab-link" data-tab="messagerie">Lire</a>
                            </li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div id="mes-sessions" class="tab-content">
            <h3 class="tab-title">Historique de vos sessions</h3>
            <div class="session-list">
                <h4 class="tab-subtitle">Sessions à venir</h4>
                <div id="upcoming-sessions-list" class="sessions-grid">
                    <?php if (empty($upcoming_sessions)): ?><p class="no-data-text">Aucune session à venir.</p><?php else: foreach ($upcoming_sessions as $s): ?>
                    <div class="session-card" data-id="<?= $s['idSession'] ?>">
                        <div class="session-header">
                            <div class="mentor-info">
                                <img src="<?= get_profile_image_path($s['pic']) ?>" class="mentor-avatar" alt="Avatar de <?= sanitize($s['p']) ?>">
                                <div class="mentor-details">
                                    <p class="mentor-name">avec <?= sanitize($s['p'].' '.$s['n']) ?></p>
                                </div>
                            </div>
                            <span class="session-status <?= $s['statutSession'] ?>"><?= $s['statutSession'] == 'en_attente' ? 'En attente' : 'Validée' ?></span>
                        </div>
                        <div class="session-content">
                            <h4 class="session-title"><?= sanitize($s['titreSession']) ?></h4>
                            <p class="session-time">
                                <i class="fas fa-calendar-day"></i> <?= date_french('l d M Y', strtotime($s['dateSession'])) ?> à <?= date('H:i', strtotime($s['heureSession'])) ?>
                                <span class="session-type-indicator">
                                    <i class="fas fa-<?= $s['typeSession'] === 'en_ligne' ? 'video' : 'map-marker-alt' ?>"></i>
                                    <?= $s['typeSession'] === 'en_ligne' ? 'En ligne' : 'Présentiel' ?>
                                </span>
                            </p>
                            <?php if ($s['typeSession'] === 'en_ligne' && !empty($s['lienReunion']) && $s['statutSession'] === 'validee'): ?>
                            <p class="session-meeting-link">
                                <a href="<?= htmlspecialchars($s['lienReunion']) ?>" target="_blank" class="meeting-link-btn-student">
                                    <i class="fas fa-video"></i> Rejoindre la réunion
                                </a>
                            </p>
                            <?php endif; ?>
                        </div>
                        <div class="session-actions">
                            <?php if($s['statutSession'] == 'en_attente'): ?>
                                <p style="color: var(--slate-500); font-style: italic; margin: 0;">En attente de validation</p>
                            <?php else: ?>
                                <button class="btn-cancel" data-id="<?= $s['idSession'] ?>">Annuler</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>

                <h4 class="tab-subtitle">Sessions passées</h4>
                <div id="past-sessions-list" class="sessions-grid">
                    <?php if (empty($past_sessions)): ?><p class="no-data-text">Aucune session passée.</p><?php else: foreach ($past_sessions as $s): ?>
                    <div class="session-card past" data-id="<?= $s['idSession'] ?>">
                        <div class="session-header">
                            <div class="mentor-info">
                                <img src="<?= get_profile_image_path($s['pic']) ?>" class="mentor-avatar" alt="Avatar de <?= sanitize($s['p']) ?>">
                                <div class="mentor-details">
                                    <p class="mentor-name">avec <?= sanitize($s['p'].' '.$s['n']) ?></p>
                                </div>
                            </div>
                            <span class="session-status terminee">Terminée</span>
                        </div>
                        <div class="session-content">
                            <h4 class="session-title"><?= sanitize($s['titreSession']) ?></h4>
                            <p class="session-time">
                                <i class="fas fa-calendar-check"></i> Le <?= date_french('d M Y', strtotime($s['dateSession'])) ?>
                                <span class="session-type-indicator">
                                    <i class="fas fa-<?= $s['typeSession'] === 'en_ligne' ? 'video' : 'map-marker-alt' ?>"></i>
                                    <?= $s['typeSession'] === 'en_ligne' ? 'En ligne' : 'Présentiel' ?>
                                </span>
                            </p>
                        </div>
                        <div class="session-actions">
                            <?php if ($s['notation'] === null): ?>
                                <button class="btn-evaluate" data-id="<?= $s['idSession'] ?>">Évaluer</button>
                            <?php else: ?>
                                <div class="rating-display" title="Votre note : <?= $s['notation'] ?>/5">
                                    <?php for($i=1;$i<=5;$i++)echo "<i class='fa".($i<=$s['notation']?'s':'r')." fa-star'></i>";?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Messagerie Tab -->
        <div id="messagerie" class="tab-content" style="padding:0;">
             <div class="chat-container">
                <div class="conversation-list">
                    <div class="chat-header" style="text-align:center"><h5>Conversations</h5></div>
                    <?php if(empty($conversations_data)): ?><p class="empty-chat" style="padding:1rem;">Aucune conversation.</p><?php else: foreach($conversations_data as $convo): ?>
                    <div class="conversation-item" data-user-id="<?= $convo['idUtilisateur'] ?>" data-user-name="<?= sanitize($convo['prenomUtilisateur'].' '.$convo['nomUtilisateur']) ?>" data-user-photo="<?= get_profile_image_path($convo['photoUrl']) ?>">
                        <div class="convo-avatar-wrapper"><img src="<?= get_profile_image_path($convo['photoUrl']) ?>"><?php if($convo['estLue'] == 0 && $convo['idExpediteur'] != $student_user_id): ?><span class="unread-dot"></span><?php endif; ?></div>
                        <div class="convo-details"><span class="convo-name"><?= sanitize($convo['prenomUtilisateur'].' '.$convo['nomUtilisateur']) ?></span><p class="convo-preview"><?= $convo['idExpediteur'] == $student_user_id ? 'Vous: ' : '' ?><?= sanitize(substr($convo['contenuMessage'], 0, 25)) ?>...</p></div>
                        <span class="convo-time"><?= date('H:i', strtotime($convo['dateEnvoi'])) ?></span>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                <div class="chat-window">
                    <div class="chat-header"><h5 id="chat-header-name">Sélectionnez une conversation</h5></div>
                    <div class="message-area" id="message-area"><p class="empty-chat">Vos messages apparaîtront ici.</p></div>
                    <form class="message-input" id="message-form" style="display: none;"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><textarea name="message" placeholder="Écrire un message..." required></textarea><button type="submit" class="btn-send"><i class="fas fa-paper-plane"></i></button></form>
                </div>
            </div>
        </div>

        <div id="disponibilites" class="tab-content">
            <h3 class="tab-title">Gérez vos Disponibilités</h3>
            <div class="availability-card">
                <div class="availability-header"><h4><i class="far fa-calendar-check"></i> Disponibilités hebdomadaires récurrentes</h4><p>Cochez les créneaux où vous êtes généralement disponible.</p></div>
                <form id="availability-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <div id="availability-feedback" style="display: none; margin-bottom: 10px;"></div>
                    <div class="availability-grid">
                        <div class="grid-header">Heure</div>
                        <?php foreach ($days_of_week as $day): ?><div class="grid-header"><?= sanitize($day) ?></div><?php endforeach; ?>
                        <?php foreach ($time_slots as $slot): ?>
                            <div class="time-label"><?= sanitize($slot) ?></div>
                            <?php foreach ($days_of_week as $day): ?>
                                <?php
                                $day_db_format = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $day));
                                $is_available = isset($availability_map[$day_db_format]) && in_array($slot, $availability_map[$day_db_format]);
                                ?>
                                <div class="time-slot">
                                    <input type="checkbox" name="slots[<?= $day_db_format ?>][]" value="<?= $slot ?>" <?= $is_available ? 'checked' : '' ?> id="slot-<?= $day_db_format ?>-<?= str_replace(':', '', $slot) ?>">
                                    <label for="slot-<?= $day_db_format ?>-<?= str_replace(':', '', $slot) ?>"></label>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="availability-actions"><button type="submit" class="btn-save-availability"><i class="fas fa-save"></i> Enregistrer</button></div>
                </form>
            </div>
        </div>
    </div>
</main>

<!-- Evaluation Modal -->
<div id="evaluation-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <button class="modal-close-btn">×</button>
        <h3>Évaluer la session</h3>
        <form id="evaluation-form">
            <input type="hidden" name="session_id" id="modal-session-id">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="form-group" style="margin-bottom:1.5rem;">
                <label>Votre note :</label>
                <div class="star-rating-input">
                    <i class="far fa-star" data-value="1"></i><i class="far fa-star" data-value="2"></i><i class="far fa-star" data-value="3"></i><i class="far fa-star" data-value="4"></i><i class="far fa-star" data-value="5"></i>
                </div>
                <input type="hidden" name="notation" id="notation-input" required>
            </div>
            <div class="form-group" style="margin-bottom:1.5rem;">
                <label for="commentaire">Votre commentaire (optionnel) :</label>
                <textarea id="commentaire" name="commentaire" rows="4" placeholder="Qu'avez-vous pensé de la session ?"></textarea>
            </div>
            <button type="submit" class="btn-primary">Envoyer l'évaluation</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Hide preloader
    const preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('preloader-hidden');
    }

    // --- Configuration & State ---
    const csrfToken = <?= json_encode($csrf_token) ?>;
    const currentUserId = <?= json_encode($student_user_id) ?>;
    let activeChatUserId = null;
    let studentChart = null;

    // --- DOM Elements ---
    const feedbackGlobal = document.getElementById('feedback-container-global');
    const tabs = document.querySelectorAll('.dashboard-tab, .tab-link');
    const tabContents = document.querySelectorAll('.tab-content');
    const availabilityForm = document.getElementById('availability-form');
    const evaluationModal = document.getElementById('evaluation-modal');
    const evaluationForm = document.getElementById('evaluation-form');
    const messageForm = document.getElementById('message-form');
    const messageArea = document.getElementById('message-area');
    const chatHeaderName = document.getElementById('chat-header-name');

    // --- Utility Functions ---
    function showGlobalFeedback(message, type = 'success') {
        if (!feedbackGlobal) return;
        feedbackGlobal.className = `message ${type}`;
        feedbackGlobal.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
        feedbackGlobal.style.display = 'block';
        setTimeout(() => { feedbackGlobal.style.display = 'none'; }, 4000);
    }

    // --- Charting ---
    function initializeChart() {
        const canvas = document.getElementById('studentChart');
        if (!canvas || studentChart) return; // Don't re-render

        try {
            const chartData = {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Sessions terminées',
                    data: <?= json_encode($chart_values) ?>,
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                }]
            };
            studentChart = new Chart(canvas, {
                type: 'line',
                data: chartData,
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
        } catch (error) {
            console.error('Error creating student chart:', error);
        }
    }

    // --- Tab Navigation ---
    function activateTab(tabName) {
        if (!tabName) return;
        tabContents.forEach(c => c.classList.remove('active'));
        tabs.forEach(t => t.classList.remove('active'));

        const newActiveContent = document.getElementById(tabName);
        if (newActiveContent) newActiveContent.classList.add('active');

        document.querySelectorAll(`.dashboard-tab[data-tab="${tabName}"], .tab-link[data-tab="${tabName}"]`).forEach(t => t.classList.add('active'));

        if (window.location.hash !== `#${tabName}`) {
            history.pushState(null, null, `#${tabName}`);
        }
        if (tabName === 'statistiques') {
            setTimeout(initializeChart, 50);
        }
    }

    // --- Event Handlers ---
    async function handleAvailabilitySubmit(e) {
        e.preventDefault();
        const feedbackDiv = document.getElementById('availability-feedback');
        if(!feedbackDiv) return;
        feedbackDiv.style.display = 'block';
        feedbackDiv.className = 'message info';
        feedbackDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
        try {
            const response = await fetch('actions/update_availability.php', { method: 'POST', body: new FormData(availabilityForm) });
            const result = await response.json();
            if (!response.ok || result.status !== 'success') throw new Error(result.message || 'La mise à jour a échoué');
            feedbackDiv.className = 'message success';
            feedbackDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${result.message}`;
        } catch (error) {
            feedbackDiv.className = 'message error';
            feedbackDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${error.message}`;
        }
        setTimeout(() => { feedbackDiv.style.display = 'none'; }, 3000);
    }
    
    // --- Initialization & Event Listeners ---

    // General click handler for dynamic elements
    document.body.addEventListener('click', e => {
        const cancelBtn = e.target.closest('.btn-cancel');
        if (cancelBtn) {
             // Add cancel session logic here
             return;
        }

        const evaluateBtn = e.target.closest('.btn-evaluate');
        if (evaluateBtn) {
            // Add evaluation modal logic here
            return;
        }

        const convoItem = e.target.closest('.conversation-item');
        if (convoItem) {
            // Add conversation click logic here
            return;
        }
    });

    // Tab listeners
    tabs.forEach(tab => {
        tab.addEventListener('click', e => {
            e.preventDefault();
            // For links that should navigate away, like "Trouver des sessions"
            if (tab.href && !tab.href.endsWith('#')) {
                 // Check if it's an absolute URL or different page
                const currentLoc = window.location.pathname.split('/').pop();
                const targetLoc = tab.pathname.split('/').pop();
                if(currentLoc !== targetLoc) {
                    window.location.href = tab.href;
                    return;
                }
            }
            activateTab(tab.dataset.tab);
        });
    });

    // Availability form
    if (availabilityForm) {
        availabilityForm.addEventListener('submit', handleAvailabilitySubmit);
    }

    // Initial load
    const initialTab = window.location.hash.substring(1) || 'statistiques';
    activateTab(initialTab);
});
</script>

<?php require_once '../includes/footer.php'; ?>
</body>
</html>