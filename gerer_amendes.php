<?php
require_once 'connexion.php';
session_start();

// Protection admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: login.php');
    exit();
}

// Notifications (comme dans gerer_reservations)
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

// Fonction format_date (corrige l'erreur undefined function)
function format_date($date_str, $format = 'd/m/Y', $default = '—') {
    if (empty($date_str) || !strtotime($date_str)) {
        return $default;
    }
    return date($format, strtotime($date_str));
}

// Récupération des amendes
$stmt = $pdo->prepare("
    SELECT a.id, a.montant, a.montant_paye, a.statut, a.date_creation, a.description,
           m.prenom, m.nom, m.numero_membre,
           l.titre
    FROM amendes a
    JOIN membres m ON a.membre_id = m.id
    LEFT JOIN emprunts e ON a.emprunt_id = e.id
    LEFT JOIN exemplaires ex ON e.exemplaire_id = ex.id
    LEFT JOIN livres l ON ex.livre_id = l.id
    ORDER BY a.date_creation DESC
");
$stmt->execute();
$amendes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Amendes - BiblioTech</title>
    
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

        .status-badge {
            padding: 6px 12px;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-paid     { background: rgba(16,185,129,0.1); color: var(--success); }
        .status-unpaid   { background: rgba(220,38,38,0.1); color: var(--danger); }
        .status-partial  { background: rgba(245,158,11,0.1); color: var(--warning); }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 6px 12px;
            border-radius: var(--radius);
            border: none;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary-small    { background: var(--primary);    color: white; }
        .btn-success-small    { background: var(--success);    color: white; }

        .btn-primary-small:hover    { background: var(--primary-light); }
        .btn-success-small:hover    { background: #059669; }

        .empty-msg {
            text-align: center;
            padding: 80px 20px;
            color: var(--gray-500);
            font-style: italic;
        }

        .reste-danger { color: var(--danger); font-weight: 600; }
        .reste-success { color: var(--success); font-weight: 600; }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="admin-header">
        <div class="admin-header-left">
            <a href="admin_dashboard.php" class="dashboard-link">
                <i class="fas fa-home"></i> Retour au Dashboard
            </a>
        </div>

        <div class="admin-header-right">
            <a href="#" class="notifications-bell" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if (count($notifications)): ?>
                    <span class="notification-badge"><?= count($notifications) ?></span>
                <?php endif; ?>
            </a>

            <div class="user-profile">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Staff') ?></strong>
                    <span>Administrateur</span>
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
                    <i class="fas fa-coins"></i>
                    Gestion des Amendes
                </div>
                <div class="section-actions">
                    <a href="#" class="btn-action"><i class="fas fa-refresh"></i> Actualiser</a>
                </div>
            </div>

            <?php if (empty($amendes)): ?>
                <div class="empty-msg">
                    <i class="fas fa-file-invoice-dollar fa-4x" style="opacity:0.3; margin-bottom:1.5rem;"></i>
                    <p>Aucune amende enregistrée pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Membre</th>
                                <th>Motif</th>
                                <th>Montant</th>
                                <th>Payé</th>
                                <th>Reste</th>
                                <th>Date</th>
                                <th>Statut</th>
                                
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($amendes as $a):
                                $reste = $a['montant'] - $a['montant_paye'];
                                $reste_class = $reste > 0 ? 'reste-danger' : 'reste-success';
                                $statut_class = $a['statut'] === 'paye' ? 'status-paid' : ($a['statut'] === 'partiel' ? 'status-partial' : 'status-unpaid');
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?></strong><br>
                                    <small style="color:var(--gray-500);"><?= htmlspecialchars($a['numero_membre']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($a['description'] ?: 'Retard / Dommage') ?></td>
                                <td><?= number_format($a['montant'], 2) ?> DH</td>
                                <td><?= number_format($a['montant_paye'], 2) ?> DH</td>
                                <td class="<?= $reste_class ?>">
                                    <?= number_format($reste, 2) ?> DH
                                </td>
                                <td><?= format_date($a['date_creation']) ?></td>
                                <td>
                                    <span class="status-badge <?= $statut_class ?>">
                                        <?= ucfirst($a['statut']) ?>
                                    </span>
                                </td>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <div style="text-align:center; margin: 3rem 0;">
            <a href="admin_dashboard.php" style="color:var(--primary); font-weight:600; text-decoration:none;">
                <i class="fas fa-arrow-left"></i> Retour au Dashboard
            </a>
        </div>

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