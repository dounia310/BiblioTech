<?php
// TOUT EN HAUT – AUCUN espace avant <?php
session_start();

require_once 'connexion.php';

// Protection admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: login.php');
    exit();
}

// Statistiques
$stats = [];
$stats['total_books']     = $pdo->query("SELECT COUNT(*) FROM livres")->fetchColumn() ?: 0;
$stats['total_members']   = $pdo->query("SELECT COUNT(*) FROM membres")->fetchColumn() ?: 0;
$stats['active_loans']    = $pdo->query("SELECT COUNT(*) FROM emprunts WHERE date_retour_effective IS NULL AND statut IN ('en_cours', 'en_retard')")->fetchColumn() ?: 0;
$stats['overdue']         = $pdo->query("SELECT COUNT(*) FROM emprunts WHERE date_retour_prevue < CURDATE() AND date_retour_effective IS NULL")->fetchColumn() ?: 0;
$stats['pending_fines']   = $pdo->query("SELECT COALESCE(SUM(montant), 0) FROM amendes WHERE statut = 'non_payee'")->fetchColumn() ?: 0;

// Notifications récentes
$stmt = $pdo->query("
    SELECT id, type, titre, message, lien, lue, created_at 
    FROM notifications 
    ORDER BY created_at DESC 
    LIMIT 8
");
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

// Fonction icônes notifications
function getNotificationIcon($type) {
    $icons = [
        'reservation' => 'calendar-check',
        'emprunt'     => 'book-reader',
        'amende'      => 'file-invoice-dollar',
        'retard'      => 'clock',
        'info'        => 'info-circle',
        'success'     => 'check-circle',
        'warning'     => 'exclamation-triangle',
    ];
    return $icons[strtolower($type ?? '')] ?? 'bell';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Admin - BiblioTech</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --primary-dark: #152a45;
            --accent: #e8b86d;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --light: #ffffff;
            --danger: #dc2626;
            --warning: #f59e0b;
            --success: #10b981;
            
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            
            --radius: 10px;
            --radius-full: 9999px;
            
            --transition: 250ms ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-100);
            color: var(--gray-700);
            line-height: 1.6;
            display: flex;
            flex-direction: column;
        }

        /* ======================================
           TOP BAR / HEADER
           ====================================== */
        .admin-header {
            background: var(--light);
            border-bottom: 1px solid var(--gray-200);
            padding: 16px 30px;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .admin-header-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-header-title i {
            color: var(--accent);
            font-size: 1.5rem;
        }

        .admin-header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notifications-bell {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: var(--gray-100);
            border-radius: var(--radius-full);
            color: var(--gray-600);
            font-size: 1.1rem;
            cursor: pointer;
            transition: all var(--transition);
            border: none;
            text-decoration: none;
        }

        .notifications-bell:hover {
            background: var(--primary);
            color: white;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: var(--radius-full);
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: var(--gray-100);
            border-radius: var(--radius-full);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-full);
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-info strong {
            font-size: 0.9rem;
            color: var(--gray-700);
        }

        .user-info span {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .logout-link {
            color: var(--danger);
            text-decoration: none;
            font-size: 0.9rem;
            padding: 8px 12px;
            border-radius: var(--radius);
            transition: all var(--transition);
        }

        .logout-link:hover {
            background: rgba(220, 38, 38, 0.1);
        }

        /* ======================================
           MAIN LAYOUT
           ====================================== */
        .admin-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* SIDEBAR */
        .admin-sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--primary-dark) 0%, var(--primary) 100%);
            color: white;
            padding: 30px 0;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 999;
            margin-top: 0;
        }

        .sidebar-logo {
            padding: 0 20px 30px;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-logo i {
            font-size: 2rem;
            color: var(--accent);
        }

        .sidebar-logo span {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-item {
            margin: 8px 12px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: rgba(255, 255, 255, 0.75);
            text-decoration: none;
            border-radius: var(--radius);
            transition: all var(--transition);
            font-weight: 500;
            font-size: 0.95rem;
        }

        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding-left: 20px;
        }

        .sidebar-link.active {
            background: rgba(232, 184, 109, 0.2);
            color: var(--accent);
            border-left: 4px solid var(--accent);
            padding-left: 12px;
        }

        .sidebar-link i {
            width: 24px;
            text-align: center;
            font-size: 1.1rem;
        }

        .sidebar-section-title {
            padding: 20px 16px 10px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 10px;
        }

        /* MAIN CONTENT */
        .admin-main {
            margin-left: 260px;
            flex: 1;
            overflow-y: auto;
            padding: 30px;
            display: flex;
            flex-direction: column;
        }

        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--light);
            border-radius: var(--radius);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            box-shadow: var(--shadow-md);
            transition: all var(--transition);
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform var(--transition);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-4px);
        }

        .stat-card:hover::before {
            transform: scaleX(1);
        }

        .stat-card.danger {
            border-left: 4px solid var(--danger);
        }

        .stat-card.warning {
            border-left: 4px solid var(--warning);
        }

        .stat-card.success {
            border-left: 4px solid var(--success);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--gray-500);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            background: var(--gray-100);
        }

        .stat-card.danger .stat-icon {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger);
        }

        .stat-card.warning .stat-icon {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .stat-card.success .stat-icon {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .stat-value {
            font-size: 2.4rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }

        .stat-change {
            font-size: 0.85rem;
            color: var(--gray-500);
        }

        /* CONTENT SECTION */
        .content-section {
            background: var(--light);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: var(--accent);
            font-size: 1.4rem;
        }

        .section-actions {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 16px;
            border-radius: var(--radius-full);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
        }

        /* NOTIFICATIONS TABLE */
        .notifications-list {
            padding: 30px;
        }

        .notification-item {
            display: flex;
            gap: 16px;
            padding: 16px;
            border-bottom: 1px solid var(--gray-200);
            align-items: flex-start;
            transition: all var(--transition);
        }

        .notification-item:hover {
            background: var(--gray-100);
            border-radius: var(--radius);
            padding-left: 20px;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .notification-icon.info {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .notification-icon.warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .notification-icon.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 4px;
        }

        .notification-message {
            font-size: 0.9rem;
            color: var(--gray-500);
            margin-bottom: 4px;
        }

        .notification-time {
            font-size: 0.8rem;
            color: var(--gray-400);
        }

        /* TABLES */
        .table-responsive {
            overflow-x: auto;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        .admin-table thead {
            background: var(--gray-100);
            border-bottom: 2px solid var(--gray-200);
        }

        .admin-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .admin-table td {
            padding: 16px;
            border-bottom: 1px solid var(--gray-200);
        }

        .admin-table tbody tr {
            transition: all var(--transition);
        }

        .admin-table tbody tr:hover {
            background: var(--gray-100);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-badge.inactive {
            background: rgba(107, 114, 128, 0.1);
            color: var(--gray-600);
        }

        .status-badge.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .status-badge.overdue {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger);
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-small {
            padding: 6px 10px;
            border-radius: var(--radius);
            border: none;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-primary-small {
            background: var(--primary);
            color: white;
        }

        .btn-primary-small:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
        }

        .btn-danger-small {
            background: var(--danger);
            color: white;
        }

        .btn-danger-small:hover {
            background: #b91c1c;
            transform: translateY(-1px);
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .admin-sidebar {
                width: 220px;
            }

            .admin-main {
                margin-left: 220px;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 16px;
            }

            .stat-value {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                width: 100%;
                height: auto;
                padding: 15px 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                display: none;
                flex-direction: row;
                overflow-x: auto;
                position: static;
            }

            .admin-sidebar.mobile-open {
                display: flex;
            }

            .admin-main {
                margin-left: 0;
                padding: 15px;
            }

            .admin-header {
                padding: 12px 15px;
            }

            .admin-header-title {
                font-size: 1.1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-value {
                font-size: 1.6rem;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .admin-table {
                font-size: 0.85rem;
            }

            .admin-table th,
            .admin-table td {
                padding: 12px 8px;
            }

            .user-info span {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .admin-header-right {
                gap: 10px;
            }

            .admin-table {
                font-size: 0.8rem;
            }

            .stat-value {
                font-size: 1.4rem;
            }

            .section-title {
                font-size: 1rem;
            }
        }

        /* ANIMATIONS */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card {
            animation: slideInUp 0.5s ease-out;
        }

        .content-section {
            animation: slideInUp 0.5s ease-out 0.1s both;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: var(--radius-full);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }
    </style>
</head>
<body>

    <!-- TOP HEADER -->
    <!-- HEADER avec notifications intégrées -->
<div class="admin-header">
    <div class="admin-header-left">
        <a href="<?= ($_SESSION['role'] ?? '') === 'membre' ? 'dashboard_membre.php' : 'admin_dashboard.php' ?>" class="dashboard-link">
            <i class="fas fa-home"></i> Retour au Dashboard
        </a>
    </div>

    <div class="admin-header-right">
        <!-- Notifications -->
        <?php
        // Récupère les 8 dernières notifications pour cet utilisateur
        $stmt_notif = $pdo->prepare("
            SELECT id, type, titre, message, lien, lue, created_at 
            FROM notifications 
            WHERE (user_id = ? OR user_id IS NULL) 
            ORDER BY created_at DESC 
            LIMIT 8
        ");
        $stmt_notif->execute([$_SESSION['user_id'] ?? 0]);
        $notifications = $stmt_notif->fetchAll(PDO::FETCH_ASSOC) ?? [];

        $nb_non_lues = count(array_filter($notifications, fn($n) => !$n['lue']));
        ?>

        <div class="notifications-bell" onclick="toggleNotifications()" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($nb_non_lues > 0): ?>
                <span class="notification-badge"><?= min($nb_non_lues, 9) . ($nb_non_lues > 9 ? '+' : '') ?></span>
            <?php endif; ?>
        </div>

        <!-- Dropdown notifications -->
        <div id="notifications-dropdown" class="notifications-dropdown" style="display:none;">
            <?php if ($notifications): ?>
                <ul>
                    <?php foreach ($notifications as $n): ?>
                        <li class="<?= $n['lue'] ? 'lue' : 'non-lue' ?>">
                            <a href="<?= htmlspecialchars($n['lien'] ?? '#') ?>" onclick="marquerLue(<?= $n['id'] ?>)">
                                <small><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></small><br>
                                <strong><?= htmlspecialchars($n['titre'] ?? 'Notification') ?></strong><br>
                                <?= htmlspecialchars($n['message']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="dropdown-footer">
                    <a href="toutes_notifications.php">Voir tout</a>
                </div>
            <?php else: ?>
                <p>Aucune notification.</p>
            <?php endif; ?>
        </div>

        <!-- Profil -->
        <div class="user-profile">
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="user-info">
                <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Utilisateur') ?></strong>
                <span><?= ucfirst($_SESSION['role'] ?? 'Membre') ?></span>
            </div>
            <a href="logout.php" class="logout-link" title="Déconnexion">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</div>

            <!-- Profil utilisateur -->
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="user-info">
                    <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></strong>
                    <span>Administrateur</span>
                </div>
                <a href="logout.php" class="logout-link" title="Déconnexion">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>

    <a href="index.php" class="home-link" title="Accueil">
        <i class="fas fa-home"></i>
    </a>

    <!-- MAIN CONTAINER -->
    <div class="admin-container">

        <!-- SIDEBAR -->
        <aside class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-logo">
                <a href="./favicon.ico"></a>
                <span>BiblioTech</span>
            </div>

            <ul class="sidebar-menu">
                <li class="sidebar-section-title">Principal</li>
                
                <li class="sidebar-item">
                    <a href="admin_dashboard.php" class="sidebar-link active">
                        <i class="fas fa-home"></i>
                        Tableau de bord
                    </a>
                </li>

                <li class="sidebar-section-title">Gestion des Ressources</li>

                <li class="sidebar-item">
                    <a href="liste_livres.php" class="sidebar-link">
                        <i class="fas fa-book"></i>
                        Livres
                    </a>
                </li>

            

                <li class="sidebar-item">
                    <a href="liste_membres.php" class="sidebar-link">
                        <i class="fas fa-users"></i>
                        Membres
                    </a>
                </li>

                <li class="sidebar-section-title">Opérations</li>

                <li class="sidebar-item">
                    <a href="gerer_emprunts.php" class="sidebar-link">
                        <i class="fas fa-exchange-alt"></i>
                        Emprunts
                    </a>
                </li>

                <li class="sidebar-item">
                    <a href="gerer_reservations.php" class="sidebar-link">
                        <i class="fas fa-calendar-check"></i>
                        Réservations
                    </a>
                </li>

                <li class="sidebar-item">
                    <a href="gerer_amendes.php" class="sidebar-link">
                        <i class="fas fa-receipt"></i>
                        Amendes
                    </a>
                </li>

                <li class="sidebar-section-title">Rapports</li>

                <li class="sidebar-item">
                    <a href="rapports.php" class="sidebar-link">
                        <i class="fas fa-chart-bar"></i>
                        Rapports
                    </a>
                </li>

                <li class="sidebar-item">
                    <a href="logs.php" class="sidebar-link">
                        <i class="fas fa-history"></i>
                        Journaux
                    </a>
                </li>
                <li class="sidebar-section-title">Utilisateurs</li>
                <a href="liste_utilisateurs.php" class="btn-action">
    <i class="fas fa-users-cog"></i> Utilisateurs
</a>
</li>

                <li class="sidebar-section-title">Paramètres</li>

                <li class="sidebar-item">
                    <a href="parametres.php" class="sidebar-link">
                        <i class="fas fa-cog"></i>
                        Paramètres
                    </a>
                </li>
            </ul>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="admin-main">

            <!-- STATISTICS CARDS -->
            <section class="stats-grid">
                <!-- Total Livres -->
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Total des Livres</div>
                            <div class="stat-value"><?php echo $stats['total_books']; ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="fas fa-arrow-up"></i> 12% depuis le mois dernier
                    </div>
                </div>

                <!-- Total Membres -->
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Membres Actifs</div>
                            <div class="stat-value"><?php echo $stats['total_members']; ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="fas fa-arrow-up"></i> 8% depuis le mois dernier
                    </div>
                </div>

                <!-- Emprunts Actifs -->
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Emprunts Actifs</div>
                            <div class="stat-value"><?php echo $stats['active_loans']; ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        En cours...
                    </div>
                </div>

                <!-- Emprunts en Retard -->
                <div class="stat-card danger">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Retards</div>
                            <div class="stat-value"><?php echo $stats['overdue']; ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="fas fa-arrow-up"></i> Attention requise
                    </div>
                </div>

                <!-- Amendes Impayées -->
                <div class="stat-card warning">
                    <div class="stat-header">
                        <div>
                            <div class="stat-label">Amendes Impayées</div>
                            <div class="stat-value"><?php echo number_format($stats['pending_fines'], 2, ',', ' '); ?> MAD</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        À collecter
                    </div>
                </div>
            </section>

            <!-- NOTIFICATIONS – VERSION PROPRE ET FONCTIONNELLE -->
        <section class="content-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-bell"></i>
                    Notifications Récentes
                </div>
                <div class="section-actions">
                    <a href="#" class="btn-action"><i class="fas fa-filter"></i> Filtrer</a>
                    <a href="#" class="btn-action"><i class="fas fa-refresh"></i> Actualiser</a>
                </div>
            </div>

            <div class="notifications-list">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item">
                            <div class="notification-icon <?= htmlspecialchars($notif['type'] ?? 'info') ?>">
                                <i class="fas fa-<?= getNotificationIcon($notif['type'] ?? 'info') ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">
                                    <?php if ($notif['lue'] == 0): ?>
                                        <span style="background:#ef4444; color:white; font-size:0.75rem; padding:3px 9px; border-radius:12px; margin-right:8px;">Non lu</span>
                                    <?php endif; ?>

                                    <?php if (!empty($notif['lien'])): ?>
                                        <a href="<?= htmlspecialchars($notif['lien']) ?>">
                                            <?= htmlspecialchars($notif['titre'] ?? 'Notification') ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($notif['titre'] ?? 'Notification') ?>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-message">
                                    <?= htmlspecialchars($notif['message'] ?? '') ?>
                                </div>
                                <div class="notification-time">
                                    <i class="fas fa-clock"></i>
                                    <?= date('d/m/Y H:i', strtotime($notif['created_at'] ?? 'now')) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:60px 20px; color:var(--gray-500);">
                        <i class="fas fa-inbox" style="font-size:3.5rem; opacity:0.4; margin-bottom:20px;"></i>
                        <p>Aucune notification récente</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
<style>
    .notifications-bell {
        position: relative;
        cursor: pointer;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.2s;
    }

    .notifications-bell:hover {
        background: var(--primary);
        color: white;
    }

    .notification-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: var(--danger);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 12px;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .notifications-dropdown {
        position: absolute;
        top: 60px;
        right: 20px;
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        box-shadow: var(--shadow-md);
        width: 320px;
        max-height: 400px;
        overflow-y: auto;
        z-index: 1000;
    }

    .notifications-dropdown ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .notifications-dropdown li {
        padding: 12px 16px;
        border-bottom: 1px solid var(--gray-200);
    }

    .notifications-dropdown li.non-lue {
        background: rgba(233, 236, 239, 0.5);
        font-weight: 500;
    }

    .notifications-dropdown li a {
        text-decoration: none;
        color: var(--gray-700);
        display: block;
    }

    .notifications-dropdown li small {
        color: var(--gray-500);
        font-size: 0.85rem;
    }

    .dropdown-footer {
        padding: 12px;
        text-align: center;
        border-top: 1px solid var(--gray-200);
    }

    .dropdown-footer a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
    }
</style>

<script>
    function toggleNotifications() {
        const dropdown = document.getElementById('notifications-dropdown');
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    }

    // Fermer le dropdown si clic ailleurs
    document.addEventListener('click', function(e) {
        const bell = document.querySelector('.notifications-bell');
        const dropdown = document.getElementById('notifications-dropdown');
        if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

    // Marquer comme lu (appel AJAX simple - à améliorer si besoin)
    function marquerLue(id) {
        fetch('marquer_lue.php?id=' + id)
            .then(() => {
                // Option : rafraîchir le badge ou supprimer la classe non-lue
            });
    }
</script>

<script>
    function toggleSidebar() {
        document.getElementById('adminSidebar').classList.toggle('mobile-open');
    }
    // Responsive menu button
    const btn = document.querySelector('.mobile-menu-btn');
    if (window.innerWidth <= 768) btn.style.display = 'block';
    window.addEventListener('resize', () => {
        btn.style.display = window.innerWidth <= 768 ? 'block' : 'none';
    });
</script>

</body>
</html>