<?php
session_start();
require_once 'connexion.php';

// Protection admin / staff
if (!isset($_SESSION['user_id']) || 
    !in_array($_SESSION['role'] ?? '', ['admin', 'bibliothecaire', 'super_admin'])) {
    header('Location: login.php');
    exit;
}

// Récupération stats rapides (exemple – adapte selon tes tables)
try {
    $stats = [
        'livres_total'       => $pdo->query("SELECT COUNT(*) FROM livres")->fetchColumn(),
        'exemplaires_dispo'  => $pdo->query("SELECT COUNT(*) FROM exemplaires WHERE statut = 'disponible'")->fetchColumn(),
        'membres_actifs'     => $pdo->query("SELECT COUNT(*) FROM membres WHERE statut = 'actif'")->fetchColumn(),
        'emprunts_en_cours'  => $pdo->query("SELECT COUNT(*) FROM emprunts WHERE statut = 'en_cours'")->fetchColumn(),
        'reservations_attente'=> $pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'en_attente'")->fetchColumn(),
        'amendes_impayees'   => $pdo->query("SELECT COALESCE(SUM(montant - montant_paye), 0) FROM amendes WHERE statut IN ('impaye','partiel')")->fetchColumn(),
        'derniers_emprunts'  => $pdo->query("SELECT e.date_emprunt, l.titre, m.nom, m.prenom 
                                             FROM emprunts e 
                                             JOIN exemplaires ex ON e.exemplaire_id = ex.id 
                                             JOIN livres l ON ex.livre_id = l.id 
                                             JOIN membres m ON e.membre_id = m.id 
                                             ORDER BY e.date_emprunt DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC),
    ];
} catch (PDOException $e) {
    $stats = []; // fallback
    $error = "Erreur stats : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Admin – Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #2b848e;
            --primary-dark: #1e6a72;
            --sidebar-bg: #254c5b;
            --sidebar-text: #e0f2f1;
            --content-bg: #f0f4f8;
            --card-bg: white;
            --danger: #c94f4f;
            --success: #7ab781;
            --warning: #f59e0b;
            --radius: 12px;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        body { margin:0; font-family:system-ui,sans-serif; background:var(--content-bg); color:#333; min-height:100vh; }
        .admin-wrapper { display:flex; min-height:100vh; }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            position: fixed;
            height: 100%;
            overflow-y: auto;
            transition: width 0.3s;
            z-index: 1000;
        }
        .sidebar-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            font-size: 1.4rem;
            font-weight: bold;
            color: white;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 1rem 0 0;
        }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: var(--sidebar-text);
            text-decoration: none;
            transition: all 0.2s;
        }
        .sidebar-menu li a:hover,
        .sidebar-menu li a.active {
            background: rgba(255,255,255,0.12);
            color: white;
        }
        .sidebar-menu i { margin-right: 12px; width: 20px; text-align: center; }
        .menu-group { margin-top: 1.5rem; padding: 0 1.5rem; font-size: 0.85rem; color: #a0d0cc; text-transform: uppercase; letter-spacing: 1px; }

        /* Main content */
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 2rem;
            transition: margin-left 0.3s;
        }
        .topbar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            border-radius: var(--radius);
        }
        .hamburger { font-size: 1.8rem; cursor: pointer; color: var(--primary); display: none; }

        /* Widgets */
        .widgets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.8rem;
        }
        .card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.8rem;
            box-shadow: var(--shadow);
            transition: transform 0.2s;
        }
        .card:hover { transform: translateY(-6px); }
        .card h3 { margin: 0 0 1.2rem; color: var(--primary); font-size: 1.35rem; }
        .stat-number { font-size: 2.6rem; font-weight: 800; color: var(--primary-dark); }
        .stat-label { color: #64748b; font-size: 0.95rem; }
        .recent-list { margin-top: 1.2rem; }
        .recent-item { padding: 0.9rem 0; border-bottom: 1px solid #eee; }
        .recent-item:last-child { border-bottom: none; }

        @media (max-width: 992px) {
            .sidebar { width: 0; overflow: hidden; }
            .sidebar.open { width: 260px; }
            .main-content { margin-left: 0; }
            .hamburger { display: block; }
        }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <!-- Sidebar (comme WP) -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">Bibliotech Admin</div>
        
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
            
            <div class="menu-group">Catalogue</div>
            <li><a href="liste_livres.php"><i class="fas fa-book"></i> Livres</a></li>
            <li><a href="ajouter_livre.php"><i class="fas fa-plus-circle"></i> Ajouter livre</a></li>
            <li><a href="gerer_reservations.php"><i class="fas fa-calendar-check"></i> Réservations</a></li>
            <li><a href="commander_livre.php"><i class="fas fa-truck"></i> Commandes fournisseurs</a></li>

            <div class="menu-group">Membres</div>
            <li><a href="liste_membres.php"><i class="fas fa-users"></i> Membres</a></li>
            <li><a href="ajouter_membre.php"><i class="fas fa-user-plus"></i> Ajouter membre</a></li>
            <li><a href="gerer_amendes.php"><i class="fas fa-coins"></i> Amendes</a></li>

            <div class="menu-group">Circulation</div>
            <li><a href="gerer_emprunts.php"><i class="fas fa-hand-holding-book"></i> Emprunts</a></li>
            <li><a href="nouvel_emprunt.php"><i class="fas fa-plus"></i> Nouvel emprunt</a></li>
            <li><a href="retour_livre.php"><i class="fas fa-undo-alt"></i> Retours</a></li>

            <div class="menu-group">Contenu & Communication</div>
            <li><a href="ajouter_actualite.php"><i class="fas fa-newspaper"></i> Actualités</a></li>
            <li><a href="ajouter_evenement.php"><i class="fas fa-calendar-plus"></i> Événements</a></li>
            <li><a href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>

            <div class="menu-group">Rapports & Outils</div>
            <li><a href="rapports.php"><i class="fas fa-chart-bar"></i> Rapports & Stats</a></li>
            <li><a href="export.php"><i class="fas fa-file-export"></i> Export / Sauvegarde</a></li>
            <li><a href="logs.php"><i class="fas fa-history"></i> Logs système</a></li>

            <div class="menu-group">Configuration</div>
            <li><a href="parametres.php"><i class="fas fa-cog"></i> Paramètres</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
        </ul>
    </nav>

    <!-- Contenu principal -->
    <main class="main-content">
        <div class="topbar">
            <div>
                <span class="hamburger" id="toggleSidebar"><i class="fas fa-bars"></i></span>
                <h2 style="margin:0; display:inline-block; margin-left:1rem;">Tableau de bord</h2>
            </div>
            <div>
                <strong><?= htmlspecialchars($_SESSION['prenom'] ?? 'Admin') ?></strong> 
                <small>(<?= ucfirst($_SESSION['role'] ?? '') ?>)</small>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div style="background:#ffebee; color:#c62828; padding:1rem; border-radius:var(--radius); margin-bottom:1.5rem;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Widgets / Stats -->
        <div class="widgets-grid">
            <div class="card">
                <h3>Vue d'ensemble</h3>
                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:1rem; margin-top:1.2rem;">
                    <div>
                        <div class="stat-number"><?= number_format($stats['livres_total'] ?? 0) ?></div>
                        <div class="stat-label">Livres</div>
                    </div>
                    <div>
                        <div class="stat-number"><?= number_format($stats['exemplaires_dispo'] ?? 0) ?></div>
                        <div class="stat-label">Disponibles</div>
                    </div>
                    <div>
                        <div class="stat-number"><?= number_format($stats['membres_actifs'] ?? 0) ?></div>
                        <div class="stat-label">Membres actifs</div>
                    </div>
                    <div>
                        <div class="stat-number"><?= number_format($stats['emprunts_en_cours'] ?? 0) ?></div>
                        <div class="stat-label">Emprunts en cours</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>Amendes en attente</h3>
                <div class="stat-number" style="color:var(--danger);">
                    <?= number_format($stats['amendes_impayees'] ?? 0, 2) ?> DH
                </div>
                <p style="margin-top:0.8rem;color:#555;">
                    <a href="gerer_amendes.php" style="color:var(--primary);">Gérer les amendes →</a>
                </p>
            </div>

            <div class="card">
                <h3>Derniers emprunts</h3>
                <div class="recent-list">
                    <?php if (!empty($stats['derniers_emprunts'])): ?>
                        <?php foreach ($stats['derniers_emprunts'] as $e): ?>
                            <div class="recent-item">
                                <strong><?= htmlspecialchars($e['titre']) ?></strong><br>
                                <small>par <?= htmlspecialchars($e['nom'].' '.$e['prenom']) ?> – <?= date('d/m/Y H:i', strtotime($e['date_emprunt'])) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#777;">Aucun emprunt récent.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tu peux ajouter d'autres widgets : réservations récentes, dernières actualités à valider, etc. -->
        </div>

        <div style="text-align:center; margin-top:3rem; color:#64748b;">
            <small>Bibliotech Admin – Version 1.0 | <?= date('Y') ?></small>
        </div>
    </main>
</div>

<script>
    // Hamburger menu mobile
    document.getElementById('toggleSidebar').addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('open');
    });
</script>

</body>
</html>