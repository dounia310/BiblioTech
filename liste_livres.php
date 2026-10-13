<?php
require_once 'connexion.php';
session_start();

// Protection admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: login.php');
    exit();
}

// Paramètres GET
$search = trim($_GET['search'] ?? '');
$sort   = $_GET['sort']   ?? 'titre_asc';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

$tri_options = [
    'titre_asc'     => ['sql' => 'l.titre ASC',           'label' => 'Titre (A → Z)'],
    'titre_desc'    => ['sql' => 'l.titre DESC',          'label' => 'Titre (Z → A)'],
    'recent_insere' => ['sql' => 'l.id DESC',             'label' => 'Plus récents insérés'],
    'ancien_insere' => ['sql' => 'l.id ASC',              'label' => 'Plus anciens insérés'],
    'populaire'     => ['sql' => 'nb_emprunts DESC',      'label' => 'Plus populaire'],
];

$order_by = $tri_options[$sort]['sql'] ?? 'l.titre ASC';

// WHERE
$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE l.titre LIKE ? OR l.isbn LIKE ? OR a.nom_complet LIKE ?";
    $like = "%$search%";
    $params = [$like, $like, $like];
}

// Count total
$count_sql = "SELECT COUNT(DISTINCT l.id) as total FROM livres l LEFT JOIN auteurs a ON l.auteur_id = a.id $where";
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total = $stmt_count->fetchColumn() ?: 0;

$totalPages = max(1, ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Requête principale
$sql = "
    SELECT 
        l.id, l.titre, l.statut_bibliographique,
        COUNT(e.id) AS total_exemplaires,
        COUNT(CASE WHEN e.statut = 'disponible' THEN 1 END) AS exemplaires_disponibles,
        COUNT(em.id) AS nb_emprunts
    FROM livres l
    LEFT JOIN auteurs a ON l.auteur_id = a.id
    LEFT JOIN exemplaires e ON l.id = e.livre_id
    LEFT JOIN emprunts em ON e.id = em.exemplaire_id
    $where
    GROUP BY l.id
    ORDER BY $order_by
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$livres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pagination helper
function pagination_link($p) {
    global $search, $sort;
    $params = ['page' => $p];
    if ($search !== '') $params['search'] = $search;
    if ($sort !== 'titre_asc') $params['sort'] = $sort;
    return '?' . http_build_query($params);
}

// Notifications
$stmt_notif = $pdo->query("SELECT id, type, titre, message, lien, lue, created_at FROM notifications ORDER BY created_at DESC LIMIT 8");
$notifications = $stmt_notif->fetchAll(PDO::FETCH_ASSOC) ?? [];

function getNotificationIcon($type) {
    $icons = [
        'reservation' => 'calendar-check', 'emprunt' => 'book-reader',
        'amende' => 'file-invoice-dollar', 'retard' => 'clock',
        'info' => 'info-circle', 'success' => 'check-circle', 'warning' => 'exclamation-triangle',
    ];
    return $icons[strtolower($type ?? '')] ?? 'bell';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue des livres – BiblioTech</title>
    
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

        .user-info strong {
            font-size: 0.9rem;
        }

        .user-info span {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .logout-link {
            color: var(--danger);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: var(--radius);
            transition: all var(--transition);
        }

        .logout-link:hover {
            background: rgba(220,38,38,0.1);
        }

        /* MENU SIMPLE VERS DASHBOARD */
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
            background: var(--primary-light);
            color: white;
        }

        /* CONTENU PRINCIPAL */
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
            margin-bottom: 30px;
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
        .admin-table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
        .admin-table thead { background: var(--gray-100); border-bottom: 2px solid var(--gray-200); }
        .admin-table th, .admin-table td { padding: 16px; text-align: left; }
        .admin-table tbody tr:hover { background: var(--gray-100); }

        .status-badge { padding: 6px 12px; border-radius: 9999px; font-size: 0.85rem; font-weight: 600; }
        .status-badge.active { background: rgba(16,185,129,0.1); color: var(--success); }
        .status-badge.overdue { background: rgba(220,38,38,0.1); color: var(--danger); }

        .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn-small { padding: 6px 10px; border-radius: var(--radius); border: none; font-size: 0.85rem; cursor: pointer; transition: all var(--transition); }
        .btn-secondary-small { background: var(--gray-500); color: white; }
        .btn-primary-small { background: var(--primary); color: white; }
        .btn-warning-small { background: var(--warning); color: white; }
        .btn-danger-small { background: var(--danger); color: white; }

        .search-form, .filters { max-width: 900px; margin: 0 auto 1.5rem; padding: 0 30px; }
        .search-input, .sort-select { padding: 10px 14px; border: 1px solid var(--gray-300); border-radius: var(--radius); background: var(--light); width: 100%; max-width: 400px; }
        .search-btn { padding: 10px 20px; background: var(--primary); color: white; border: none; border-radius: var(--radius); cursor: pointer; }
        .pagination { text-align: center; margin: 2rem 0; }
        .pagination a, .pagination span { padding: 8px 14px; margin: 0 4px; border-radius: var(--radius); color: var(--primary); background: var(--light); border: 1px solid var(--gray-200); text-decoration: none; }
        .pagination .current { background: var(--primary); color: white; }
    </style>
</head>
<body>

    <!-- HEADER SIMPLIFIÉ AVEC MENU DASHBOARD -->
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
                    <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></strong>
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

        <!-- LIVRES SECTION -->
        <section class="content-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-book"></i>
                    Catalogue des Livres
                </div>
                <div class="section-actions">
                    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'bibliothecaire', 'super_admin'])): ?>
                        <a href="ajouter_livre.php" class="btn-action"><i class="fas fa-plus"></i> Ajouter un Livre</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recherche -->
            <div class="search-form">
                <form method="GET">
                    <input type="text" name="search" class="search-input" placeholder="Rechercher par titre, ISBN ou auteur..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="search-btn"><i class="fas fa-search"></i> Rechercher</button>
                    <?php if ($search !== ''): ?>
                        <a href="liste_livres.php?sort=<?= urlencode($sort) ?>" class="clear">✖ Effacer</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tri -->
            <div class="filters">
                <form method="GET">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <select name="sort" class="sort-select" onchange="this.form.submit()">
                        <?php foreach ($tri_options as $key => $opt): ?>
                            <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opt['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if (count($livres) > 0): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Exemplaires</th>
                                <th>Dispo.</th>
                                <th>Statut</th>
                                <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'bibliothecaire', 'super_admin'])): ?>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($livres as $l): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($l['titre']) ?></strong></td>
                                <td><?= $l['total_exemplaires'] ?></td>
                                <td>
                                    <span class="status-badge <?= $l['exemplaires_disponibles'] > 0 ? 'active' : 'overdue' ?>">
                                        <?= $l['exemplaires_disponibles'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($l['statut_bibliographique'] ?: '—') ?></td>

                                <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin','bibliothecaire','super_admin'])): ?>
                                <td class="action-buttons">
                                    <button class="btn-small btn-secondary-small" onclick="location.href='details_livre.php?id=<?= $l['id'] ?>'">
                                        <i class="fas fa-eye"></i> Détails
                                    </button>
                                    <button class="btn-small btn-primary-small" onclick="location.href='modifier_livre.php?id=<?= $l['id'] ?>'">
                                        <i class="fas fa-edit"></i> Modifier
                                    </button>
                                    <button class="btn-small btn-warning-small" onclick="location.href='ajouter_exemplaire.php?id=<?= $l['id'] ?>'">
                                        <i class="fas fa-plus"></i> Ajouter exemplaire(s)
                                    </button>
                                    <button class="btn-small btn-danger-small" onclick="if(confirm('Supprimer « <?= addslashes($l['titre']) ?> » ?')) location.href='supprimer_livre.php?id=<?= $l['id'] ?>';">
                                        <i class="fas fa-trash-alt"></i> Supprimer
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= pagination_link($page - 1) ?>">« Précédent</a>
                    <?php else: ?>
                        <span class="disabled">« Précédent</span>
                    <?php endif; ?>

                    <?php
                    $range = 2;
                    $start = max(1, $page - $range);
                    $end   = min($totalPages, $page + $range);

                    if ($start > 1) echo '<a href="' . pagination_link(1) . '">1</a><span>...</span>';
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a href="<?= pagination_link($i) ?>" class="<?= $i === $page ? 'current' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($end < $totalPages) echo '<span>...</span><a href="' . pagination_link($totalPages) . '">' . $totalPages . '</a>'; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= pagination_link($page + 1) ?>">Suivant »</a>
                    <?php else: ?>
                        <span class="disabled">Suivant »</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="no-result">
                    <i class="fas fa-book-open fa-3x" style="opacity:0.4; margin-bottom:1rem;"></i><br>
                    <?php if ($search !== ''): ?>
                        Aucun résultat pour « <?= htmlspecialchars($search) ?> ».<br>
                        <small>Essayez d'autres termes ou <a href="liste_livres.php">voir tout</a>.</small>
                    <?php else: ?>
                        Aucun livre pour le moment.<br>
                        <small>Ajoutez-en un (admin).</small>
                    <?php endif; ?>
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