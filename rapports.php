<?php
require_once 'connexion.php';
session_start();

// Protection admin / super_admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    header("Location: login.php");
    exit();
}

// Notifications (comme dans toutes tes pages admin)
$stmt_notif = $pdo->query("
    SELECT id, type, titre, message, lien, lue, created_at 
    FROM notifications 
    ORDER BY created_at DESC 
    LIMIT 8
");
$notifications = $stmt_notif->fetchAll(PDO::FETCH_ASSOC) ?? [];

// Dates par défaut (mois en cours)
$debut = $_GET['debut'] ?? date('Y-m-01');
$fin = $_GET['fin'] ?? date('Y-m-t');

// Statistiques générales
$stats = [];
$stats['livres'] = $pdo->query("SELECT COUNT(*) FROM livres")->fetchColumn();
$stats['membres'] = $pdo->query("SELECT COUNT(*) FROM membres")->fetchColumn();
$stats['emprunts_total'] = $pdo->query("SELECT COUNT(*) FROM emprunts")->fetchColumn();
$stats['emprunts_mois'] = $pdo->query("SELECT COUNT(*) FROM emprunts WHERE date_emprunt BETWEEN '$debut' AND '$fin'")->fetchColumn();

// Emprunts par type de membre ce mois-ci
try {
    $emprunts_par_type = $pdo->query("
        SELECT m.type_membre, COUNT(e.id) as nombre
        FROM emprunts e
        JOIN membres m ON e.membre_id = m.id
        WHERE e.date_emprunt BETWEEN '$debut' AND '$fin'
        GROUP BY m.type_membre
    ")->fetchAll();
} catch (Exception $e) {
    $emprunts_par_type = [];
}

// Livres les plus empruntés ce mois-ci
try {
    $livres_populaires = $pdo->query("
        SELECT l.titre, COUNT(e.id) as nb_emprunts
        FROM emprunts e
        JOIN exemplaires ex ON e.exemplaire_id = ex.id
        JOIN livres l ON ex.livre_id = l.id
        WHERE e.date_emprunt BETWEEN '$debut' AND '$fin'
        GROUP BY l.id
        ORDER BY nb_emprunts DESC
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $livres_populaires = [];
}

// Membres les plus actifs ce mois-ci
try {
    $membres_actifs = $pdo->query("
        SELECT m.nom, m.prenom, COUNT(e.id) as nb_emprunts
        FROM emprunts e
        JOIN membres m ON e.membre_id = m.id
        WHERE e.date_emprunt BETWEEN '$debut' AND '$fin'
        GROUP BY e.membre_id
        ORDER BY nb_emprunts DESC
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $membres_actifs = [];
}

// Récupérer les paramètres système
$parametres = [];
try {
    $stmt = $pdo->query("SELECT cle, valeur FROM parametres WHERE categorie IN ('emprunt', 'amende', 'membre')");
    $parametres = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $parametres = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports et Statistiques - BiblioTech</title>
    
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

        .period-form {
            padding: 1.5rem 2rem;
            background: var(--gray-100);
            border-bottom: 1px solid var(--gray-200);
        }

        .period-form form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }

        .period-form label {
            font-weight: 600;
            color: var(--gray-600);
        }

        .period-form input[type="date"] {
            padding: 10px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
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
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--primary);
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.95rem;
        }

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

        .export-links {
            padding: 1.5rem 2rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #059669; }

        .alert-warning {
            background: rgba(245,158,11,0.1);
            color: var(--warning);
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

        .back-link:hover { color: var(--primary-light); }
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
                    <i class="fas fa-chart-line"></i>
                    Rapports et Statistiques
                </div>
            </div>

            <!-- Période d'analyse -->
            <div class="period-form">
                <form method="GET">
                    <label for="debut">Du :</label>
                    <input type="date" id="debut" name="debut" value="<?= htmlspecialchars($debut) ?>" required>
                    
                    <label for="fin">Au :</label>
                    <input type="date" id="fin" name="fin" value="<?= htmlspecialchars($fin) ?>" required>
                    
                    <button type="submit" class="btn btn-primary">Actualiser</button>
                </form>
                <p style="margin-top: 10px; color: var(--gray-600);">
                    Période : <?= date('d/m/Y', strtotime($debut)) ?> au <?= date('d/m/Y', strtotime($fin)) ?>
                </p>
            </div>

            <!-- Statistiques générales -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['livres']) ?></div>
                    <div class="stat-label">Livres dans la bibliothèque</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['membres']) ?></div>
                    <div class="stat-label">Membres inscrits</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['emprunts_total']) ?></div>
                    <div class="stat-label">Emprunts (tous temps)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= number_format($stats['emprunts_mois']) ?></div>
                    <div class="stat-label">Emprunts cette période</div>
                </div>
            </div>

            <!-- Emprunts par type de membre -->
            <h2 style="padding: 0 2rem; margin: 2rem 0 1rem;">Emprunts par type de membre</h2>
            <?php if (count($emprunts_par_type) > 0): ?>
                <div class="table-responsive" style="padding: 0 2rem;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Type de membre</th>
                                <th>Nombre d'emprunts</th>
                                <th>Pourcentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_emprunts_type = array_sum(array_column($emprunts_par_type, 'nombre'));
                            foreach ($emprunts_par_type as $e): 
                                $pourcentage = $total_emprunts_type > 0 ? round(($e['nombre'] / $total_emprunts_type) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><?= ucfirst($e['type_membre']) ?></td>
                                <td><?= $e['nombre'] ?></td>
                                <td><?= $pourcentage ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert-warning" style="margin: 0 2rem;">Aucun emprunt enregistré pour cette période.</div>
            <?php endif; ?>

            <!-- Livres les plus populaires -->
            <h2 style="padding: 0 2rem; margin: 2rem 0 1rem;">Livres les plus populaires (top 10)</h2>
            <?php if (count($livres_populaires) > 0): ?>
                <div class="table-responsive" style="padding: 0 2rem;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Livre</th>
                                <th>Nombre d'emprunts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($livres_populaires as $l): ?>
                            <tr>
                                <td><?= htmlspecialchars($l['titre']) ?></td>
                                <td><?= $l['nb_emprunts'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert-warning" style="margin: 0 2rem;">Aucun livre emprunté pour cette période.</div>
            <?php endif; ?>

            <!-- Membres les plus actifs -->
            <h2 style="padding: 0 2rem; margin: 2rem 0 1rem;">Membres les plus actifs (top 10)</h2>
            <?php if (count($membres_actifs) > 0): ?>
                <div class="table-responsive" style="padding: 0 2rem;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Membre</th>
                                <th>Nombre d'emprunts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($membres_actifs as $m): ?>
                            <tr>
                                <td><?= htmlspecialchars($m['nom'] . ' ' . $m['prenom']) ?></td>
                                <td><?= $m['nb_emprunts'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert-warning" style="margin: 0 2rem;">Aucun emprunt pour cette période.</div>
            <?php endif; ?>

            <!-- Paramètres système -->
            <h2 style="padding: 0 2rem; margin: 2rem 0 1rem;">Paramètres système</h2>
            <?php if (count($parametres) > 0): ?>
                <div class="table-responsive" style="padding: 0 2rem;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Paramètre</th>
                                <th>Valeur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parametres as $cle => $valeur): ?>
                            <tr>
                                <td><?= htmlspecialchars($cle) ?></td>
                                <td><?= htmlspecialchars($valeur) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert-warning" style="margin: 0 2rem;">Aucun paramètre trouvé.</div>
            <?php endif; ?>

            <!-- Export -->
            <div class="export-links" style="padding: 1.5rem 2rem; display: flex; flex-wrap: wrap; gap: 1rem;">
                <a href="export.php?debut=<?= urlencode($debut) ?>&fin=<?= urlencode($fin) ?>" class="btn btn-success">
                    <i class="fas fa-file-export"></i> Exporter ce rapport (CSV)
                </a>
                <a href="export.php" class="btn btn-success">
                    <i class="fas fa-users"></i> Exporter la liste des membres
                </a>
                <a href="export.php" class="btn btn-success">
                    <i class="fas fa-book"></i> Exporter le catalogue
                </a>
                <a href="export.php?debut=<?= urlencode($debut) ?>&fin=<?= urlencode($fin) ?>" class="btn btn-success">
                    <i class="fas fa-sync"></i> Exporter les emprunts
                </a>
            </div>

        </section>

        <div style="text-align:center; margin: 3rem 0;">
            <a href="admin_dashboard.php" style="color:var(--primary); font-weight:600; text-decoration:none;">
                <i class="fas fa-arrow-left"></i> Retour au Dashboard
            </a>
        </div>

    </div>

</body>
</html>