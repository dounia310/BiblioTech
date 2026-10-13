<?php
require_once 'connexion.php';
session_start();

// Protection admin / super_admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    header("Location: login.php");
    exit();
}

// Notifications (comme dans tes autres pages)
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
function format_date($date_str, $format = 'd/m/Y H:i:s', $default = '—') {
    if (empty($date_str) || !strtotime($date_str)) {
        return $default;
    }
    return date($format, strtotime($date_str));
}

// Nombre de logs par page
$logs_par_page = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $logs_par_page;

// Filtres
$action_filter = $_GET['action'] ?? '';
$table_filter  = trim($_GET['table'] ?? '');
$user_filter   = trim($_GET['user'] ?? '');

// Construction WHERE
$where = [];
$params = [];

if ($action_filter) {
    $where[] = "l.action = ?";
    $params[] = $action_filter;
}
if ($table_filter) {
    $where[] = "l.table_name LIKE ?";
    $params[] = "%$table_filter%";
}
if ($user_filter) {
    $where[] = "u.username LIKE ?";
    $params[] = "%$user_filter%";
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Total logs (avec filtres)
$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM logs_audit l LEFT JOIN utilisateurs u ON l.user_id = u.id $where_clause");
$total_stmt->execute($params);
$total_logs = $total_stmt->fetchColumn();
$total_pages = max(1, ceil($total_logs / $logs_par_page));

// Logs paginés
$sql = "
    SELECT l.*, u.username 
    FROM logs_audit l
    LEFT JOIN utilisateurs u ON l.user_id = u.id
    $where_clause
    ORDER BY l.timestamp DESC
    LIMIT :offset, :limit
";
$stmt = $pdo->prepare($sql);
foreach ($params as $i => $val) {
    $stmt->bindValue($i + 1, $val);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $logs_par_page, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vider les logs
if (isset($_GET['vider_logs']) && $_GET['vider_logs'] == 1) {
    try {
        $pdo->exec("TRUNCATE TABLE logs_audit");
        $message = "Tous les logs ont été vidés avec succès.";
        header("Location: logs.php?message=" . urlencode($message));
        exit();
    } catch (PDOException $e) {
        $error = "Erreur lors du vidage des logs : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs Système - BiblioTech</title>
    
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

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding: 1.5rem 2rem;
            justify-content: center;
            border-top: 1px solid var(--gray-200);
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

        .btn-danger-small     { background: var(--danger);     color: white; }
        .btn-success-small    { background: var(--success);    color: white; }
        .btn-primary-small    { background: var(--primary);    color: white; }

        .btn-danger-small:hover     { background: #b91c1c; }
        .btn-success-small:hover    { background: #059669; }
        .btn-primary-small:hover    { background: var(--primary-light); }

        .filters {
            padding: 1.5rem 2rem;
            background: var(--gray-100);
            border-bottom: 1px solid var(--gray-200);
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-600);
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius);
            font-size: 0.95rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            padding: 1.5rem 2rem;
        }

        .stat-card {
            background: var(--light);
            border-radius: var(--radius);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.95rem;
        }

        .empty-msg {
            text-align: center;
            padding: 80px 20px;
            color: var(--gray-500);
            font-style: italic;
        }

        .alert-success, .alert-danger {
            padding: 1rem;
            border-radius: var(--radius);
            margin: 1rem 2rem;
            text-align: center;
        }

        .alert-success { background: rgba(16,185,129,0.1); color: var(--success); }
        .alert-danger  { background: rgba(220,38,38,0.1); color: var(--danger); }
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

        <section class="content-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-history"></i>
                    Logs Système
                </div>
                <div class="section-actions">
                    <a href="?vider_logs=1" class="btn-small btn-danger-small" onclick="return confirm('Vider TOUS les logs ? Cette action est irréversible !');">
                        <i class="fas fa-trash-alt"></i> Vider les logs
                    </a>
                </div>
            </div>

            <?php if (isset($_GET['message'])): ?>
                <div class="alert-success"><?= htmlspecialchars($_GET['message']) ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Statistiques rapides -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_logs ?></div>
                    <div class="stat-label">Total des logs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $total_pages ?></div>
                    <div class="stat-label">Pages</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $logs_par_page ?></div>
                    <div class="stat-label">Logs par page</div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="filters">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label for="action">Action</label>
                        <select name="action" id="action">
                            <option value="">Toutes</option>
                            <option value="INSERT" <?= $action_filter === 'INSERT' ? 'selected' : '' ?>>INSERT</option>
                            <option value="UPDATE" <?= $action_filter === 'UPDATE' ? 'selected' : '' ?>>UPDATE</option>
                            <option value="DELETE" <?= $action_filter === 'DELETE' ? 'selected' : '' ?>>DELETE</option>
                            <option value="LOGIN" <?= $action_filter === 'LOGIN' ? 'selected' : '' ?>>LOGIN</option>
                            <option value="LOGOUT" <?= $action_filter === 'LOGOUT' ? 'selected' : '' ?>>LOGOUT</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="table">Table</label>
                        <input type="text" name="table" id="table" value="<?= htmlspecialchars($table_filter) ?>" placeholder="ex: membres">
                    </div>
                    <div class="filter-group">
                        <label for="user">Utilisateur</label>
                        <input type="text" name="user" id="user" value="<?= htmlspecialchars($user_filter) ?>" placeholder="Nom d'utilisateur">
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn-small btn-primary-small">Filtrer</button>
                        <a href="logs.php" class="btn-small btn-secondary-small">Réinitialiser</a>
                    </div>
                </form>
            </div>

            <?php if (empty($logs)): ?>
                <div class="empty-msg">
                    <i class="fas fa-history fa-4x" style="opacity:0.3; margin-bottom:1.5rem;"></i>
                    <p>Aucun log trouvé.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Date/Heure</th>
                                <th>Table</th>
                                <th>Action</th>
                                <th>Utilisateur</th>
                                <th>ID Record</th>
                                <th>Anciennes valeurs</th>
                                <th>Nouvelles valeurs</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= format_date($log['timestamp']) ?></td>
                                <td><?= htmlspecialchars($log['table_name']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($log['action']) ?>">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($log['username'] ?: 'Système') ?></td>
                                <td><?= $log['record_id'] ?: '—' ?></td>
                                <td>
                                    <?php if ($log['old_values']): ?>
                                        <pre style="margin:0; font-size:0.85rem;"><?= htmlspecialchars($log['old_values']) ?></pre>
                                    <?php else: ?>
                                        <em>—</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['new_values']): ?>
                                        <pre style="margin:0; font-size:0.85rem;"><?= htmlspecialchars($log['new_values']) ?></pre>
                                    <?php else: ?>
                                        <em>—</em>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div style="text-align:center; margin: 2rem 0;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&action=<?= urlencode($action_filter) ?>&table=<?= urlencode($table_filter) ?>&user=<?= urlencode($user_filter) ?>" class="btn-small btn-secondary-small">Précédent</a>
                    <?php endif; ?>

                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?page=<?= $i ?>&action=<?= urlencode($action_filter) ?>&table=<?= urlencode($table_filter) ?>&user=<?= urlencode($user_filter) ?>" 
                           class="btn-small <?= $i == $page ? 'btn-primary-small' : 'btn-secondary-small' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page+1 ?>&action=<?= urlencode($action_filter) ?>&table=<?= urlencode($table_filter) ?>&user=<?= urlencode($user_filter) ?>" class="btn-small btn-secondary-small">Suivant</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>

        </section>

        <div style="text-align:center; margin: 3rem 0;">
            <a href="admin_dashboard.php" style="color:var(--primary); font-weight:600; text-decoration:none;">
                <i class="fas fa-arrow-left"></i> Retour au Dashboard
            </a>
        </div>

    </div>

</body>
</html>