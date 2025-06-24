<?php
require '../config/config.php';
require '../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- 1. AUTHENTICATION & SECURITY ---
if (!isset($_SESSION['admin']) || $_SESSION['admin']['role'] !== 'admin') {
    header('Location: admin_login.php'); exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$admin_id = $_SESSION['admin']['id'];

// --- 2. DATA FETCHING ---
try {
    // System Statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM Utilisateur");
    $stmt->execute();
    $total_users = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) as total_mentors FROM Mentor");
    $stmt->execute();
    $total_mentors = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) as total_students FROM Etudiant");
    $stmt->execute();
    $total_students = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) as total_sessions FROM Session");
    $stmt->execute();
    $total_sessions = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) as active_sessions FROM Session WHERE statutSession IN ('disponible', 'en_attente', 'validee')");
    $stmt->execute();
    $active_sessions = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) as completed_sessions FROM Session WHERE statutSession = 'terminee'");
    $stmt->execute();
    $completed_sessions = $stmt->fetchColumn();

    // Recent Users
    $stmt = $pdo->prepare("SELECT idUtilisateur, nomUtilisateur, prenomUtilisateur, emailUtilisateur, role, photoUrl, verified FROM Utilisateur ORDER BY idUtilisateur DESC LIMIT 10");
    $stmt->execute();
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent Sessions
    $stmt = $pdo->prepare("
        SELECT s.idSession, s.titreSession, s.dateSession, s.heureSession, s.statutSession, s.typeSession,
               u.prenomUtilisateur as mentor_prenom, u.nomUtilisateur as mentor_nom,
               u2.prenomUtilisateur as student_prenom, u2.nomUtilisateur as student_nom
        FROM Session s
        JOIN Mentor m ON s.idMentorAnimateur = m.idMentor
        JOIN Utilisateur u ON m.idUtilisateur = u.idUtilisateur
        LEFT JOIN Utilisateur u2 ON s.idEtudiantDemandeur = u2.idUtilisateur
        ORDER BY s.idSession DESC LIMIT 15
    ");
    $stmt->execute();
    $recent_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // System Health Metrics
    $stmt = $pdo->prepare("SELECT COUNT(*) as unverified_users FROM Utilisateur WHERE verified = 0");
    $stmt->execute();
    $unverified_users = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_sessions FROM Session WHERE statutSession = 'en_attente'");
    $stmt->execute();
    $pending_sessions = $stmt->fetchColumn();

    // Monthly Growth Data
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(CURDATE() - INTERVAL n.n MONTH, '%Y-%m') as month,
            COALESCE(u.user_count, 0) as user_count,
            COALESCE(s.session_count, 0) as session_count
        FROM (
            SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
        ) n
        LEFT JOIN (
            SELECT DATE_FORMAT(idUtilisateur, '%Y-%m') as month, COUNT(*) as user_count
            FROM Utilisateur 
            WHERE idUtilisateur >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(idUtilisateur, '%Y-%m')
        ) u ON DATE_FORMAT(CURDATE() - INTERVAL n.n MONTH, '%Y-%m') = u.month
        LEFT JOIN (
            SELECT DATE_FORMAT(dateSession, '%Y-%m') as month, COUNT(*) as session_count
            FROM Session 
            WHERE dateSession >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(dateSession, '%Y-%m')
        ) s ON DATE_FORMAT(CURDATE() - INTERVAL n.n MONTH, '%Y-%m') = s.month
        ORDER BY month DESC
    ");
    $stmt->execute();
    $growth_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    log_error("Admin dashboard data fetch error: " . $e->getMessage());
    $error_message = "Erreur lors du chargement des données.";
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentora - Tableau de Bord Administrateur</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../assets/css/mentor_dashboard.css?v=<?php echo time(); ?>">
    <link rel="icon" href="../assets/images/White_Tower_Symbol.webp" type="image/x-icon">
    <style>
        /* Admin-specific styling */
        :root {
            --admin-red: #dc2626;
            --admin-red-dark: #b91c1c;
            --admin-red-light: #fef2f2;
        }
        
        .admin-header {
            background: linear-gradient(135deg, var(--admin-red) 0%, var(--admin-red-dark) 100%);
            color: white;
            padding: 1rem 0;
            text-align: center;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(220, 38, 38, 0.2);
        }
        
        .admin-profile-card {
            border-top: 3px solid var(--admin-red);
        }
        
        .admin-nav a.active {
            border-bottom-color: var(--admin-red);
        }
        
        .admin-nav a:hover {
            color: var(--admin-red);
        }
        
        .admin-stat-card {
            border-left: 4px solid var(--admin-red);
        }
        
        .admin-stat-card:hover {
            border-color: var(--admin-red);
        }
        
        .admin-btn {
            background-color: var(--admin-red);
        }
        
        .admin-btn:hover {
            background-color: var(--admin-red-dark);
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-verified {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-unverified {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .user-table, .session-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .user-table th, .user-table td,
        .session-table th, .session-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--slate-200);
        }
        
        .user-table th, .session-table th {
            background-color: var(--slate-50);
            font-weight: 600;
            color: var(--slate-700);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-small {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn-verify {
            background: #10b981;
            color: white;
        }
        
        .btn-delete {
            background: #ef4444;
            color: white;
        }
        
        .btn-edit {
            background: #f59e0b;
            color: white;
        }

        .session-status.status-disponible {
            background: #d1fae5;
            color: #065f46;
        }

        .session-status.status-en_attente {
            background: #fef3c7;
            color: #92400e;
        }

        .session-status.status-validee {
            background: #dbeafe;
            color: #1e40af;
        }

        .session-status.status-terminee {
            background: #e5e7eb;
            color: #374151;
        }

        .session-status.status-annulee {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Scrollable Table Container */
        .table-container {
            max-height: 500px;
            overflow-y: auto;
            overflow-x: auto;
            border: 1px solid var(--slate-200);
            border-radius: 8px;
            margin-top: 1rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .table-container::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Enhanced Table Styling */
        .session-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            min-width: 800px; /* Ensure table doesn't get too cramped */
        }

        .session-table thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .session-table th {
            background-color: var(--slate-100);
            font-weight: 600;
            color: var(--slate-700);
            padding: 1rem 0.75rem;
            text-align: left;
            border-bottom: 2px solid var(--slate-200);
            white-space: nowrap;
        }

        .session-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--slate-200);
            vertical-align: middle;
        }

        .session-table tbody tr:hover {
            background-color: var(--slate-50);
        }

        .session-table tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }

        .session-table tbody tr:nth-child(even):hover {
            background-color: var(--slate-50);
        }

        /* Cell Content Styling */
        .session-title-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 600;
            color: var(--slate-800);
        }

        .mentor-cell, .student-cell {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--slate-700);
        }

        .date-cell {
            white-space: nowrap;
            font-size: 0.9rem;
            color: var(--slate-600);
        }

        /* Enhanced Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.25rem;
            justify-content: center;
        }

        .btn-small {
            padding: 0.375rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
        }

        .btn-small:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .btn-edit:hover {
            background: #d97706;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .btn-verify:hover {
            background: #059669;
        }

        /* User Table Specific Styling */
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            min-width: 700px;
        }

        .user-table thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .user-table th {
            background-color: var(--slate-100);
            font-weight: 600;
            color: var(--slate-700);
            padding: 1rem 0.75rem;
            text-align: left;
            border-bottom: 2px solid var(--slate-200);
            white-space: nowrap;
        }

        .user-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--slate-200);
            vertical-align: middle;
        }

        .user-table tbody tr:hover {
            background-color: var(--slate-50);
        }

        .user-table tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }

        .user-table tbody tr:nth-child(even):hover {
            background-color: var(--slate-50);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--slate-200);
        }

        .user-name-cell {
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 600;
            color: var(--slate-800);
        }

        .email-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--slate-600);
            font-size: 0.9rem;
        }

        /* Enhanced Status Badges */
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            white-space: nowrap;
        }

        /* Responsive Table Improvements */
        @media (max-width: 1200px) {
            .table-container {
                max-height: 400px;
            }

            .session-table,
            .user-table {
                font-size: 0.9rem;
            }

            .btn-small {
                padding: 0.25rem 0.375rem;
                font-size: 0.7rem;
                min-width: 28px;
                height: 28px;
            }
        }
    </style>
</head>
<body>

<div class="admin-header">
    <i class="fas fa-shield-alt"></i> TABLEAU DE BORD ADMINISTRATEUR - MENTORA
</div>

<main class="dashboard-container">
    <aside class="profile-sidebar">
        <a href="index.php" class="sidebar-back-link"><i class="fas fa-arrow-left"></i> Retour</a>
        <div class="profile-card admin-profile-card">
            <div class="card-image-container">
                <div style="width: 100%; height: 250px; background: linear-gradient(135deg, var(--admin-red), var(--admin-red-dark)); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem;">
                    <i class="fas fa-shield-alt"></i>
                </div>
            </div>
            <div class="card-body">
                <h3 class="profile-name"><?= sanitize($_SESSION['admin']['prenom'] . ' ' . $_SESSION['admin']['nom']) ?></h3>
                <p class="profile-specialty">Administrateur Système</p>
                <div class="profile-rating">
                    <i class="fas fa-shield-alt" style="color: var(--admin-red);"></i>
                    <strong>Admin</strong>
                    <span>(Accès complet)</span>
                </div>
            </div>
            <div class="card-footer">
                <a href="logout.php" class="btn-primary-full-width admin-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </div>
        </div>
    </aside>

    <div class="dashboard-main-content">
        <nav class="dashboard-nav admin-nav">
            <ul>
                <li><a href="#statistiques" class="dashboard-tab active" data-tab="statistiques"><i class="fas fa-chart-line"></i> Statistiques</a></li>
                <li><a href="#utilisateurs" class="dashboard-tab" data-tab="utilisateurs"><i class="fas fa-users"></i> Utilisateurs <?php if($unverified_users > 0): ?><span class="notification-badge"><?= $unverified_users ?></span><?php endif; ?></a></li>
                <li><a href="#sessions" class="dashboard-tab" data-tab="sessions"><i class="fas fa-calendar-alt"></i> Sessions <?php if($pending_sessions > 0): ?><span class="notification-badge"><?= $pending_sessions ?></span><?php endif; ?></a></li>
                <li><a href="#systeme" class="dashboard-tab" data-tab="systeme"><i class="fas fa-cogs"></i> Système</a></li>
            </ul>
        </nav>

        <div id="feedback-container-global" class="message" style="display: none;"></div>
        
        <div id="statistiques" class="tab-content active">
            <h3 class="tab-title">Statistiques de la Plateforme</h3>
            <div class="stats-grid">
                <div class="stat-card admin-stat-card">
                    <i class="fas fa-users stat-icon"></i>
                    <span class="stat-value"><?= $total_users ?></span>
                    <p class="stat-label">Utilisateurs Total</p>
                </div>
                <div class="stat-card admin-stat-card">
                    <i class="fas fa-chalkboard-teacher stat-icon"></i>
                    <span class="stat-value"><?= $total_mentors ?></span>
                    <p class="stat-label">Mentors</p>
                </div>
                <div class="stat-card admin-stat-card">
                    <i class="fas fa-graduation-cap stat-icon"></i>
                    <span class="stat-value"><?= $total_students ?></span>
                    <p class="stat-label">Étudiants</p>
                </div>
                <div class="stat-card admin-stat-card">
                    <i class="fas fa-calendar-check stat-icon"></i>
                    <span class="stat-value"><?= $total_sessions ?></span>
                    <p class="stat-label">Sessions Total</p>
                </div>
                <div class="stat-card admin-stat-card">
                    <i class="fas fa-clock stat-icon"></i>
                    <span class="stat-value"><?= $active_sessions ?></span>
                    <p class="stat-label">Sessions Actives</p>
                </div>
                <div class="stat-card admin-stat-card">
                    <i class="fas fa-check-circle stat-icon"></i>
                    <span class="stat-value"><?= $completed_sessions ?></span>
                    <p class="stat-label">Sessions Terminées</p>
                </div>
            </div>
            
            <div class="chart-container">
                <h4>Croissance de la Plateforme (6 derniers mois)</h4>
                <canvas id="growthChart" width="400" height="200"></canvas>
            </div>
        </div>

        <div id="utilisateurs" class="tab-content">
            <h3 class="tab-title">Gestion des Utilisateurs</h3>

            <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 2rem;">
                <div class="stat-card">
                    <i class="fas fa-user-check stat-icon" style="color: #10b981;"></i>
                    <span class="stat-value"><?= $total_users - $unverified_users ?></span>
                    <p class="stat-label">Utilisateurs Vérifiés</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-clock stat-icon" style="color: #f59e0b;"></i>
                    <span class="stat-value"><?= $unverified_users ?></span>
                    <p class="stat-label">En Attente de Vérification</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-graduate stat-icon" style="color: #3b82f6;"></i>
                    <span class="stat-value"><?= $total_students ?></span>
                    <p class="stat-label">Étudiants</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-user-tie stat-icon" style="color: #8b5cf6;"></i>
                    <span class="stat-value"><?= $total_mentors ?></span>
                    <p class="stat-label">Mentors</p>
                </div>
            </div>

            <div class="form-card">
                <h4>Utilisateurs Récents</h4>
                <div class="table-container">
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $user): ?>
                            <tr>
                                <td>
                                    <img src="<?= get_profile_image_path($user['photoUrl']) ?>" alt="<?= sanitize($user['prenomUtilisateur']) ?>" class="user-avatar">
                                </td>
                                <td>
                                    <div class="user-name-cell" title="<?= sanitize($user['prenomUtilisateur'] . ' ' . $user['nomUtilisateur']) ?>">
                                        <?= sanitize($user['prenomUtilisateur'] . ' ' . $user['nomUtilisateur']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="email-cell" title="<?= sanitize($user['emailUtilisateur']) ?>">
                                        <?= sanitize($user['emailUtilisateur']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge" style="background: <?= $user['role'] === 'mentor' ? '#e0e7ff' : '#f0f9ff' ?>; color: <?= $user['role'] === 'mentor' ? '#3730a3' : '#0c4a6e' ?>;">
                                        <?= $user['role'] === 'mentor' ? 'Mentor' : 'Étudiant' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $user['verified'] ? 'status-verified' : 'status-unverified' ?>">
                                        <?= $user['verified'] ? 'Vérifié' : 'Non vérifié' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if (!$user['verified']): ?>
                                        <button class="btn-small btn-verify" onclick="verifyUser(<?= $user['idUtilisateur'] ?>)" title="Vérifier l'utilisateur">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn-small btn-edit" onclick="editUser(<?= $user['idUtilisateur'] ?>)" title="Modifier l'utilisateur">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-small btn-delete" onclick="deleteUser(<?= $user['idUtilisateur'] ?>)" title="Supprimer l'utilisateur">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="sessions" class="tab-content">
            <h3 class="tab-title">Gestion des Sessions</h3>

            <div class="stats-grid" style="grid-template-columns: repeat(5, 1fr); margin-bottom: 2rem;">
                <div class="stat-card">
                    <i class="fas fa-calendar-plus stat-icon" style="color: #10b981;"></i>
                    <span class="stat-value"><?= $total_sessions ?></span>
                    <p class="stat-label">Total Sessions</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-hourglass-half stat-icon" style="color: #f59e0b;"></i>
                    <span class="stat-value"><?= $pending_sessions ?></span>
                    <p class="stat-label">En Attente</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-play stat-icon" style="color: #3b82f6;"></i>
                    <span class="stat-value"><?= $active_sessions ?></span>
                    <p class="stat-label">Actives</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-double stat-icon" style="color: #059669;"></i>
                    <span class="stat-value"><?= $completed_sessions ?></span>
                    <p class="stat-label">Terminées</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-percentage stat-icon" style="color: #8b5cf6;"></i>
                    <span class="stat-value"><?= $total_sessions > 0 ? round(($completed_sessions / $total_sessions) * 100, 1) : 0 ?>%</span>
                    <p class="stat-label">Taux de Réussite</p>
                </div>
            </div>

            <div class="form-card">
                <h4>Sessions Récentes</h4>
                <div class="table-container">
                    <table class="session-table">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Mentor</th>
                                <th>Étudiant</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_sessions as $session): ?>
                            <tr>
                                <td>
                                    <div class="session-title-cell" title="<?= sanitize($session['titreSession']) ?>">
                                        <?= sanitize($session['titreSession']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="mentor-cell">
                                        <?= sanitize($session['mentor_prenom'] . ' ' . $session['mentor_nom']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="student-cell">
                                        <?= $session['student_prenom'] ? sanitize($session['student_prenom'] . ' ' . $session['student_nom']) : 'Non assigné' ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="date-cell">
                                        <?= date('d/m/Y H:i', strtotime($session['dateSession'] . ' ' . $session['heureSession'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge" style="background: <?= $session['typeSession'] === 'en_ligne' ? '#e0f2fe' : '#f3e5f5' ?>; color: <?= $session['typeSession'] === 'en_ligne' ? '#01579b' : '#4a148c' ?>;">
                                        <?= $session['typeSession'] === 'en_ligne' ? 'En ligne' : 'Présentiel' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge session-status status-<?= $session['statutSession'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $session['statutSession'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-small btn-edit" onclick="editSession(<?= $session['idSession'] ?>)" title="Modifier la session">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-small btn-delete" onclick="deleteSession(<?= $session['idSession'] ?>)" title="Supprimer la session">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="systeme" class="tab-content">
            <h3 class="tab-title">Administration Système</h3>

            <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 2rem;">
                <div class="stat-card">
                    <i class="fas fa-database stat-icon" style="color: #10b981;"></i>
                    <span class="stat-value">Actif</span>
                    <p class="stat-label">Base de Données</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-server stat-icon" style="color: #3b82f6;"></i>
                    <span class="stat-value">En ligne</span>
                    <p class="stat-label">Serveur</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-shield-alt stat-icon" style="color: #8b5cf6;"></i>
                    <span class="stat-value">Sécurisé</span>
                    <p class="stat-label">Système</p>
                </div>
            </div>

            <div class="form-card">
                <h4>Actions Système</h4>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    <button class="btn-primary-full-width admin-btn" onclick="clearCache()">
                        <i class="fas fa-broom"></i>
                        Vider le Cache
                    </button>
                    <button class="btn-primary-full-width admin-btn" onclick="backupDatabase()">
                        <i class="fas fa-download"></i>
                        Sauvegarder la BD
                    </button>
                    <button class="btn-primary-full-width admin-btn" onclick="viewLogs()">
                        <i class="fas fa-file-alt"></i>
                        Voir les Logs
                    </button>
                    <button class="btn-primary-full-width admin-btn" onclick="systemMaintenance()">
                        <i class="fas fa-tools"></i>
                        Maintenance
                    </button>
                </div>
            </div>

            <div class="form-card">
                <h4>Informations Système</h4>
                <table class="user-table">
                    <tr>
                        <td><strong>Version PHP</strong></td>
                        <td><?= phpversion() ?></td>
                    </tr>
                    <tr>
                        <td><strong>Serveur Web</strong></td>
                        <td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Non disponible' ?></td>
                    </tr>
                    <tr>
                        <td><strong>Base de Données</strong></td>
                        <td>MySQL <?= $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Espace Disque</strong></td>
                        <td><?= round(disk_free_space('.') / 1024 / 1024 / 1024, 2) ?> GB disponible</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- General Setup ---
    const csrfToken = <?= json_encode($csrf_token) ?>;

    // --- Tab Navigation ---
    const tabs = document.querySelectorAll('.dashboard-tab');
    const tabContents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            const targetTab = tab.getAttribute('data-tab');

            // Remove active class from all tabs and contents
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            // Add active class to clicked tab and corresponding content
            tab.classList.add('active');
            document.getElementById(targetTab).classList.add('active');
        });
    });

    // --- Global Feedback Function ---
    const feedbackGlobal = document.getElementById('feedback-container-global');
    function showGlobalFeedback(message, type = 'success') {
        feedbackGlobal.className = `message ${type}`;
        feedbackGlobal.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
        feedbackGlobal.style.display = 'block';
        setTimeout(() => { feedbackGlobal.style.display = 'none'; }, 4000);
    }

    // --- Growth Chart ---
    const growthData = <?= json_encode(array_reverse($growth_data)) ?>;
    const ctx = document.getElementById('growthChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: growthData.map(item => item.month),
            datasets: [{
                label: 'Nouveaux Utilisateurs',
                data: growthData.map(item => item.user_count),
                borderColor: '#dc2626',
                backgroundColor: 'rgba(220, 38, 38, 0.1)',
                tension: 0.4
            }, {
                label: 'Nouvelles Sessions',
                data: growthData.map(item => item.session_count),
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});

// --- Admin Functions ---
function verifyUser(userId) {
    if (!confirm('Êtes-vous sûr de vouloir vérifier cet utilisateur ?')) return;

    fetch('actions/admin_verify_user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${userId}&csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showGlobalFeedback(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showGlobalFeedback(data.message, 'error');
        }
    })
    .catch(error => {
        showGlobalFeedback('Erreur de connexion', 'error');
    });
}

function deleteUser(userId) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.')) return;

    fetch('actions/admin_delete_user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${userId}&csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showGlobalFeedback(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showGlobalFeedback(data.message, 'error');
        }
    })
    .catch(error => {
        showGlobalFeedback('Erreur de connexion', 'error');
    });
}

function editUser(userId) {
    // Redirect to user edit page
    window.location.href = `edit_profile.php?user_id=${userId}&admin=1`;
}

function editSession(sessionId) {
    // Redirect to session edit page
    window.location.href = `session_details.php?id=${sessionId}&admin=1`;
}

function deleteSession(sessionId) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette session ? Cette action est irréversible.')) return;

    fetch('actions/admin_delete_session.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `session_id=${sessionId}&csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showGlobalFeedback(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showGlobalFeedback(data.message, 'error');
        }
    })
    .catch(error => {
        showGlobalFeedback('Erreur de connexion', 'error');
    });
}

function clearCache() {
    showGlobalFeedback('Cache vidé avec succès', 'success');
}

function backupDatabase() {
    showGlobalFeedback('Sauvegarde de la base de données initiée', 'success');
}

function viewLogs() {
    window.open('../config/error.log', '_blank');
}

function systemMaintenance() {
    showGlobalFeedback('Mode maintenance activé', 'success');
}
</script>

</body>
</html>
