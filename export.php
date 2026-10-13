<?php
require_once 'connexion.php';
session_start();

// Protection admin / super_admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    header("Location: login.php");
    exit();
}

// Notifications (comme dans toutes les pages admin)
$stmt_notif = $pdo->query("
    SELECT id, type, titre, message, lien, lue, created_at 
    FROM notifications 
    ORDER BY created_at DESC 
    LIMIT 8
");
$notifications = $stmt_notif->fetchAll(PDO::FETCH_ASSOC) ?? [];

// Gestion de l'export
$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? '';
$message = '';

if ($type && $format) {
    switch ($type) {
        case 'livres':
            $query = "SELECT 
                l.isbn, l.titre, l.auteur, l.annee_publication, l.langue,
                l.genre_principal, l.note_moyenne, l.popularite,
                COUNT(DISTINCT e.id) as nb_exemplaires,
                COUNT(DISTINCT CASE WHEN e.statut = 'disponible' THEN e.id END) as exemplaires_disponibles,
                COUNT(DISTINCT emp.id) as fois_empruntes
                FROM livres l
                LEFT JOIN exemplaires e ON l.id = e.livre_id
                LEFT JOIN emprunts emp ON e.id = emp.exemplaire_id
                GROUP BY l.id
                ORDER BY l.titre";
            $filename = 'liste_livres';
            break;
            
        case 'membres':
            $query = "SELECT 
                m.numero_membre, m.nom, m.prenom, m.email, m.telephone,
                m.type_membre, m.date_inscription, m.date_expiration, m.statut,
                COUNT(DISTINCT e.id) as emprunts_actuels,
                (SELECT COUNT(*) FROM reservations WHERE membre_id = m.id AND statut IN ('en_attente', 'disponible')) as reservations_actives,
                (SELECT COALESCE(SUM(montant - montant_paye), 0) FROM amendes WHERE membre_id = m.id AND statut IN ('impaye', 'partiel')) as amendes_impayees
                FROM membres m
                LEFT JOIN emprunts e ON m.id = e.membre_id AND e.statut = 'en_cours'
                GROUP BY m.id
                ORDER BY m.nom, m.prenom";
            $filename = 'liste_membres';
            break;
            
        case 'emprunts':
            $query = "SELECT 
                e.id, e.date_emprunt, e.date_retour_prevue, e.date_retour_effective, e.statut,
                m.numero_membre, CONCAT(m.nom, ' ', m.prenom) as membre,
                l.titre, ex.code_barre,
                DATEDIFF(COALESCE(e.date_retour_effective, CURDATE()), e.date_retour_prevue) as jours_retard,
                e.amende_cumulee
                FROM emprunts e
                JOIN membres m ON e.membre_id = m.id
                JOIN exemplaires ex ON e.exemplaire_id = ex.id
                JOIN livres l ON ex.livre_id = l.id
                WHERE e.date_emprunt >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                ORDER BY e.date_emprunt DESC";
            $filename = 'historique_emprunts';
            break;
            
        case 'rapports':
            $query = "SELECT 
                s.date, s.type, s.valeur, s.metadata
                FROM statistiques s
                WHERE s.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ORDER BY s.date DESC, s.type";
            $filename = 'rapports_statistiques';
            break;
            
        case 'logs':
            $query = "SELECT 
                l.timestamp, l.table_name, l.record_id, l.action,
                l.user_id, l.user_type, l.ip_address
                FROM logs_audit l
                WHERE l.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY l.timestamp DESC
                LIMIT 1000";
            $filename = 'logs_audit';
            break;
            
        default:
            $message = "Type d'export non reconnu.";
            break;
    }
    
    if (isset($query)) {
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Journaliser l'export
            $log = $pdo->prepare("
                INSERT INTO logs_audit (table_name, action, user_id, user_type, ip_address)
                VALUES ('export', 'EXPORT', ?, 'staff', ?)
            ");
            $log->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
            
            // Générer le fichier selon le format
            switch ($format) {
                case 'csv':
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');
                    
                    $output = fopen('php://output', 'w');
                    if (!empty($data)) fputcsv($output, array_keys($data[0]));
                    foreach ($data as $row) fputcsv($output, $row);
                    fclose($output);
                    exit();
                    
                case 'excel':
                    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.xls"');
                    
                    echo '<table border="1">';
                    if (!empty($data)) {
                        echo '<tr>';
                        foreach (array_keys($data[0]) as $header) echo '<th>' . htmlspecialchars($header) . '</th>';
                        echo '</tr>';
                    }
                    foreach ($data as $row) {
                        echo '<tr>';
                        foreach ($row as $cell) echo '<td>' . htmlspecialchars($cell) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    exit();
                    
                case 'pdf':
                    // Version simplifiée (HTML → PDF via navigateur)
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.pdf"');
                    
                    $html = '<html><head><style>
                        body { font-family: Arial, sans-serif; }
                        h1 { color: #333; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th { background-color: #f2f2f2; padding: 8px; border: 1px solid #ddd; }
                        td { padding: 8px; border: 1px solid #ddd; }
                    </style></head><body>';
                    $html .= '<h1>' . ucfirst($type) . ' - BiblioTech</h1>';
                    $html .= '<p>Export généré le: ' . date('d/m/Y H:i') . '</p>';
                    
                    if (!empty($data)) {
                        $html .= '<table>';
                        $html .= '<tr>';
                        foreach (array_keys($data[0]) as $header) $html .= '<th>' . htmlspecialchars($header) . '</th>';
                        $html .= '</tr>';
                        foreach ($data as $row) {
                            $html .= '<tr>';
                            foreach ($row as $cell) $html .= '<td>' . htmlspecialchars($cell) . '</td>';
                            $html .= '</tr>';
                        }
                        $html .= '</table>';
                    } else {
                        $html .= '<p>Aucune donnée à exporter.</p>';
                    }
                    $html .= '</body></html>';
                    
                    echo $html;
                    exit();
                    
                default:
                    $message = "Format non supporté.";
            }
        } catch (PDOException $e) {
            $message = "Erreur lors de l'export : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export de Données - BiblioTech</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-700: #334155;
            --light: #ffffff;
            --danger: #dc2626;
            --success: #10b981;
            --radius: 10px;
        }

        body { font-family: 'Inter', sans-serif; background: var(--gray-100); color: var(--gray-700); margin: 0; min-height: 100vh; }

        .admin-header {
            background: var(--light);
            border-bottom: 1px solid var(--gray-200);
            padding: 16px 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-link { color: var(--primary); font-weight: 600; text-decoration: none; }

        .main-content {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .content-section {
            background: var(--light);
            border-radius: var(--radius);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .section-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 20px 30px;
        }

        .section-title {
            margin: 0;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .export-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
        }

        .export-card {
            background: var(--gray-100);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .export-card h3 {
            margin-top: 0;
            color: var(--primary);
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .export-buttons {
            margin-top: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
        }

        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-csv { background: #28a745; color: white; }
        .btn-excel { background: #17a2b8; color: white; }
        .btn-pdf { background: #dc3545; color: white; }

        .btn:hover { opacity: 0.9; transform: translateY(-1px); }

        .alert-danger {
            background: rgba(220,38,38,0.1);
            color: var(--danger);
            padding: 1rem;
            border-radius: var(--radius);
            margin: 1rem 2rem;
            text-align: center;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            margin: 2rem auto;
            display: block;
            text-align: center;
        }
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

    <!-- CONTENU -->
    <div class="main-content">

        <section class="content-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-file-export"></i>
                    Export de Données
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert-danger"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="export-grid">
                <!-- Livres -->
                <div class="export-card">
                    <h3><i class="fas fa-book"></i> Liste des livres</h3>
                    <p>Export du catalogue complet avec statistiques.</p>
                    <div class="export-buttons">
                        <a href="?type=livres&format=csv" class="btn btn-csv">CSV</a>
                        <a href="?type=livres&format=excel" class="btn btn-excel">Excel</a>
                        <a href="?type=livres&format=pdf" class="btn btn-pdf">PDF</a>
                    </div>
                </div>

                <!-- Membres -->
                <div class="export-card">
                    <h3><i class="fas fa-users"></i> Liste des membres</h3>
                    <p>Membres avec emprunts en cours et amendes.</p>
                    <div class="export-buttons">
                        <a href="?type=membres&format=csv" class="btn btn-csv">CSV</a>
                        <a href="?type=membres&format=excel" class="btn btn-excel">Excel</a>
                        <a href="?type=membres&format=pdf" class="btn btn-pdf">PDF</a>
                    </div>
                </div>

                <!-- Emprunts -->
                <div class="export-card">
                    <h3><i class="fas fa-book-open"></i> Historique des emprunts (6 mois)</h3>
                    <p>Emprunts récents avec détails.</p>
                    <div class="export-buttons">
                        <a href="?type=emprunts&format=csv" class="btn btn-csv">CSV</a>
                        <a href="?type=emprunts&format=excel" class="btn btn-excel">Excel</a>
                        <a href="?type=emprunts&format=pdf" class="btn btn-pdf">PDF</a>
                    </div>
                </div>

                <!-- Rapports -->
                <div class="export-card">
                    <h3><i class="fas fa-chart-bar"></i> Rapports statistiques (30 jours)</h3>
                    <p>Statistiques journalières.</p>
                    <div class="export-buttons">
                        <a href="?type=rapports&format=csv" class="btn btn-csv">CSV</a>
                        <a href="?type=rapports&format=excel" class="btn btn-excel">Excel</a>
                        <a href="?type=rapports&format=pdf" class="btn btn-pdf">PDF</a>
                    </div>
                </div>

                <!-- Logs -->
                <div class="export-card">
                    <h3><i class="fas fa-history"></i> Logs d'audit (7 jours)</h3>
                    <p>Activité système récente.</p>
                    <div class="export-buttons">
                        <a href="?type=logs&format=csv" class="btn btn-csv">CSV</a>
                        <a href="?type=logs&format=excel" class="btn btn-excel">Excel</a>
                        <a href="?type=logs&format=pdf" class="btn btn-pdf">PDF</a>
                    </div>
                </div>
            </div>
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