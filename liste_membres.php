<?php
require_once 'connexion.php';
session_start();

// Protection staff
if (!isset($_SESSION['user_id']) || 
    !in_array($_SESSION['role'], ['bibliothecaire', 'admin', 'super_admin'])) {
    header("Location: index.php");
    exit;
}

// Récupération des membres
try {
    $membres = $pdo->query("
        SELECT 
            m.id,
            m.numero_membre,
            m.nom,
            m.prenom,
            m.email,
            m.telephone
        FROM membres m
        ORDER BY m.nom ASC, m.prenom ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors du chargement des membres : " . $e->getMessage();
}

// Notifications (comme dans liste_livres)
$stmt_notif = $pdo->query("
    SELECT id, type, titre, message, lien, lue, created_at 
    FROM notifications 
    ORDER BY created_at DESC 
    LIMIT 8
");
$notifications = $stmt_notif->fetchAll(PDO::FETCH_ASSOC) ?? [];

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
    <title>Liste des Membres - BiblioTech</title>
    
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
            --radius: 10px;
            --transition: 250ms ease;
        }

        * { margin:0; padding:0; box-sizing:border-box; }

        html, body {
            height: auto !important;
            min-height: 100vh;
            overflow-y: auto !important;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--gray-100);
            color: var(--gray-700);
            line-height: 1.6;
        }

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

        .dashboard-link {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: var(--radius);
            transition: all var(--transition);
        }

        .dashboard-link:hover {
            background: var(--primary);
            color: white;
        }

        .admin-header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notifications-bell {
            position: relative;
            width: 44px;
            height: 44px;
            background: var(--gray-100);
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-600);
            font-size: 1.1rem;
            cursor: pointer;
            transition: all var(--transition);
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
            border-radius: 9999px;
            width: 24px;
            height: 24px;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: var(--gray-100);
            border-radius: 9999px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 9999px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-info strong { font-size: 0.9rem; }
        .user-info span { font-size: 0.75rem; color: var(--gray-500); }

        .logout-link {
            color: var(--danger);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: var(--radius);
            transition: all var(--transition);
        }

        .logout-link:hover { background: rgba(220,38,38,0.1); }

        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

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

        .section-title i { color: var(--accent); font-size: 1.4rem; }

        .table-responsive { overflow-x: auto; }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        .admin-table thead {
            background: var(--gray-100);
            border-bottom: 2px solid var(--gray-200);
        }

        .admin-table th, .admin-table td {
            padding: 16px;
            text-align: left;
        }

        .admin-table tbody tr:hover { background: var(--gray-100); }

        .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }

        .btn-small {
            padding: 6px 10px;
            border-radius: var(--radius);
            border: none;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all var(--transition);
        }

        .btn-primary-small    { background: var(--primary);    color: white; }
        .btn-success-small    { background: var(--success);    color: white; }
        .btn-warning-small    { background: var(--warning);    color: white; }
        .btn-danger-small     { background: var(--danger);     color: white; }
        .btn-secondary-small  { background: var(--gray-500);   color: white; }

        .btn-primary-small:hover    { background: var(--primary-light); transform: translateY(-1px); }
        .btn-success-small:hover    { background: #059669; transform: translateY(-1px); }
        .btn-warning-small:hover    { background: #d97706; transform: translateY(-1px); }
        .btn-danger-small:hover     { background: #b91c1c; transform: translateY(-1px); }
        .btn-secondary-small:hover  { background: var(--gray-600); transform: translateY(-1px); }

        .search-form, .filters { max-width: 900px; margin: 0 auto 1.5rem; padding: 0 30px; }
        .search-input, .sort-select { padding: 10px 14px; border: 1px solid var(--gray-300); border-radius: var(--radius); background: var(--light); width: 100%; max-width: 400px; }
        .search-btn { padding: 10px 20px; background: var(--primary); color: white; border: none; border-radius: var(--radius); cursor: pointer; }

        @media (max-width: 768px) {
            .admin-header { padding: 12px 15px; flex-wrap: wrap; gap: 10px; }
            .main-content { padding: 15px; }
        }
    </style>
</head>
<body>

    <!-- HEADER SIMPLIFIÉ AVEC LIEN DASHBOARD -->
    <div class="admin-header">
        <div class="admin-header-left">
            <a href="admin_dashboard.php" class="dashboard-link">
                <i class="fas fa-home"></i> Retour au Dashboard
            </a>
        </div>

        <div class="admin-header-right">
            <a href="#" class="notifications-bell" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if (count($notifications) > 0): ?>
                    <span class="notification-badge"><?= count($notifications) ?></span>
                <?php endif; ?>
            </a>

            <div class="user-profile">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Staff') ?></strong>
                    <span><?= ucfirst($_SESSION['role'] ?? 'Bibliothécaire') ?></span>
                </div>
                <a href="logout.php" class="logout-link" title="Déconnexion">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- CONTENU PRINCIPAL -->
    <div class="main-content">

        <section class="content-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-users"></i>
                    Liste des Membres
                </div>
                <div class="section-actions">
                    <a href="ajouter_membre.php" class="btn-small btn-primary-small">
                        <i class="fas fa-user-plus"></i> Ajouter un membre
                    </a>
                </div>
                <div class="section-actions">
                    <a href="#" class="btn-action"><i class="fas fa-refresh"></i> Actualiser</a>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div style="background:#fee2e2; color:#dc2626; padding:1.5rem; text-align:center; margin:1rem;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($membres)): ?>
                <div style="text-align:center; padding:80px 20px; color:var(--gray-500);">
                    <i class="fas fa-user-slash fa-4x" style="opacity:0.3; margin-bottom:1.5rem;"></i>
                    <p>Aucun membre inscrit pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>N° Membre</th>
                                <th>Nom Prénom</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($membres as $m): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($m['numero_membre'] ?? '—') ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($m['nom'] . ' ' . $m['prenom']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($m['email'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($m['telephone'] ?: '—') ?></td>
                                <td class="action-buttons">
                                    <a href="voir_membre.php?id=<?= $m['id'] ?>" class="btn-small btn-secondary-small">
                                        <i class="fas fa-eye"></i> Détails
                                    </a>
                                    <a href="modifier_membre.php?id=<?= $m['id'] ?>" class="btn-small btn-primary-small">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <a href="gerer_amendes_membre.php?id=<?= $m['id'] ?>" class="btn-small btn-warning-small">
                                        <i class="fas fa-coins"></i> Amendes
                                    </a>
                                    <a href="historique_emprunts_membre.php?id=<?= $m['id'] ?>" class="btn-small btn-secondary-small">
                                        <i class="fas fa-history"></i> Historique
                                    </a>
                                    <button class="btn-small btn-danger-small" 
                                            onclick="if(confirm('SUPPRIMER DÉFINITIVEMENT <?= addslashes($m['nom'].' '.$m['prenom']) ?> ?')) location.href='supprimer_membre.php?id=<?= $m['id'] ?>';">
                                        <i class="fas fa-trash-alt"></i> Supprimer
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
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

</body>
</html>