<?php
require_once 'connexion.php';
session_start();


// Protection staff
if (!isset($_SESSION['user_id']) || 
    !in_array($_SESSION['role'], ['bibliothecaire', 'admin', 'super_admin'])) {
    header("Location: login.php");
    exit;
}

$membre_id = (int)($_GET['id'] ?? 0);
if ($membre_id <= 0) {
    header("Location: liste_membres.php");
    exit;
}

// Notifications (pour le header)
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

// Récupération membre + emprunts + amendes
try {
    $stmt = $pdo->prepare("SELECT * FROM membres WHERE id = ?");
    $stmt->execute([$membre_id]);
    $membre = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if (!$membre) {
        header("Location: liste_membres.php?error=Membre non trouvé");
        exit;
    }

    // Emprunts en cours
    $emprunts_stmt = $pdo->prepare("
        SELECT 
            e.id,
            e.date_emprunt,
            e.date_retour_prevue,
            l.titre,
            l.isbn,
            DATEDIFF(CURDATE(), e.date_retour_prevue) AS jours_retard
        FROM emprunts e
        JOIN exemplaires ex ON e.exemplaire_id = ex.id
        JOIN livres l ON ex.livre_id = l.id
        WHERE e.membre_id = ? AND e.date_retour_effective IS NULL
        ORDER BY e.date_retour_prevue ASC
    ");
    $emprunts_stmt->execute([$membre_id]);
    $emprunts_en_cours = $emprunts_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Amendes impayées – motif rendu optionnel pour éviter l'erreur SQL
    // Remplace la requête actuelle par celle-ci (sans motif)
$amendes_stmt = $pdo->prepare("
    SELECT 
        id, 
        montant, 
        date_creation, 
        statut
    FROM amendes
    WHERE membre_id = ? 
      AND statut IN ('impaye', 'partiel')
    ORDER BY date_creation DESC
");
    $amendes_stmt->execute([$membre_id]);
    $amendes_impayees = $amendes_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (PDOException $e) {
    $error = "Erreur base de données : " . htmlspecialchars($e->getMessage());
}

// Helpers
function safe($value, $default = '—') {
    return $value !== null && trim($value) !== '' ? htmlspecialchars($value) : $default;
}

function format_date($date) {
    return $date && strtotime($date) ? date('d/m/Y', strtotime($date)) : '—';
}

$nom_complet = safe(($membre['prenom'] ?? '') . ' ' . ($membre['nom'] ?? ''), 'Membre inconnu');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil – <?= $nom_complet ?> | BiblioTech</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --accent: #e8b86d;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --light: #ffffff;
            --danger: #dc2626;
            --warning: #f59e0b;
            --success: #10b981;
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

        .info-list {
            padding: 1.5rem 2rem;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .info-row:last-child { border-bottom: none; }

        .info-icon {
            font-size: 1.4rem;
            color: var(--primary);
            width: 40px;
            text-align: center;
        }

        .info-label {
            font-weight: 600;
            color: var(--gray-600);
            min-width: 160px;
            font-size: 0.95rem;
        }

        .info-value {
            flex: 1;
            color: var(--gray-700);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-active   { background: rgba(16,185,129,0.1); color: var(--success); }
        .status-suspended { background: rgba(220,38,38,0.1); color: var(--danger); }
        .status-pending  { background: rgba(245,158,11,0.1); color: var(--warning); }

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
            border-top: 1px solid var(--gray-200);
            justify-content: center;
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
        .btn-warning-small    { background: var(--warning);    color: white; }
        .btn-danger-small     { background: var(--danger);     color: white; }
        .btn-secondary-small  { background: var(--gray-500);   color: white; }

        .btn-primary-small:hover    { background: var(--primary-light); }
        .btn-success-small:hover    { background: #059669; }
        .btn-warning-small:hover    { background: #d97706; }
        .btn-danger-small:hover     { background: #b91c1c; }
        .btn-secondary-small:hover  { background: var(--gray-600); }

        .empty-msg {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray-500);
            font-style: italic;
        }

        .alert-danger {
            background: #fee2e2;
            color: #dc2626;
            padding: 1rem;
            border-radius: var(--radius);
            margin: 1rem 2rem;
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
                    <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Staff') ?></strong>
                    <span><?= ucfirst($_SESSION['role'] ?? 'Bibliothécaire') ?></span>
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
                    <i class="fas fa-user-circle"></i>
                    Profil de <?= $nom_complet ?>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <?php if ($membre): ?>

                <!-- Infos personnelles – une ligne par champ -->
                <div class="info-list">
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-id-card"></i></div>
                        <div class="info-label">N° membre</div>
                        <div class="info-value"><strong><?= safe($membre['numero_membre']) ?></strong></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-envelope"></i></div>
                        <div class="info-label">Email</div>
                        <div class="info-value"><?= safe($membre['email']) ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-phone"></i></div>
                        <div class="info-label">Téléphone</div>
                        <div class="info-value"><?= safe($membre['telephone'], 'Non renseigné') ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-home"></i></div>
                        <div class="info-label">Adresse</div>
                        <div class="info-value"><?= safe($membre['adresse'] ?? '—') ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-calendar-day"></i></div>
                        <div class="info-label">Date de naissance</div>
                        <div class="info-value"><?= format_date($membre['date_naissance'] ?? '') ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="info-label">Date d'inscription</div>
                        <div class="info-value"><?= format_date($membre['date_inscription'] ?? '') ?></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-user-check"></i></div>
                        <div class="info-label">Statut</div>
                        <div class="info-value">
                            <?php
                            $statut = strtolower(safe($membre['statut'], 'inconnu'));
                            $classe = $statut === 'actif' ? 'status-active' : ($statut === 'suspendu' ? 'status-suspended' : 'status-pending');
                            ?>
                            <span class="status-badge <?= $classe ?>"><?= ucfirst($statut) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="action-buttons">
                    <a href="modifier_membre.php?id=<?= $membre_id ?>" class="btn-small btn-primary-small">
                        <i class="fas fa-edit"></i> Modifier
                    </a>
                    <a href="supprimer_membre.php?id=<?= $membre_id ?>" class="btn-small btn-danger-small" onclick="return confirm('Supprimer définitivement ce membre ?')">
                        <i class="fas fa-trash-alt"></i> Supprimer
                    </a>
                    <a href="mailto:<?= safe($membre['email']) ?>" class="btn-small btn-success-small">
                        <i class="fas fa-envelope"></i> Contacter
                    </a>
                    <a href="nouvel_emprunt.php?membre_id=<?= $membre_id ?>" class="btn-small btn-primary-small">
                        <i class="fas fa-book-reader"></i> Nouvel emprunt
                    </a>
                    <a href="historique_membre.php?id=<?= $membre_id ?>" class="btn-small btn-secondary-small">
                        <i class="fas fa-history"></i> Historique
                    </a>
                </div>

                <!-- Emprunts en cours -->
                <div class="content-section" style="margin-top: 2rem;">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-book-open"></i>
                            Emprunts en cours (<?= count($emprunts_en_cours) ?>)
                        </div>
                    </div>

                    <?php if ($emprunts_en_cours): ?>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Livre</th>
                                        <th>ISBN</th>
                                        <th>Emprunt le</th>
                                        <th>Retour prévu</th>
                                        <th>Retard</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($emprunts_en_cours as $e): ?>
                                    <tr>
                                        <td><strong><?= safe($e['titre']) ?></strong></td>
                                        <td><?= safe($e['isbn']) ?></td>
                                        <td><?= format_date($e['date_emprunt']) ?></td>
                                        <td><?= format_date($e['date_retour_prevue']) ?></td>
                                        <td>
                                            <?php if ($e['jours_retard'] > 0): ?>
                                                <span class="status-badge status-danger">
                                                    <i class="fas fa-clock"></i> <?= $e['jours_retard'] ?> j
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-active">À temps</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="action-buttons">
                                            <a href="retour_livre.php?emprunt_id=<?= $e['id'] ?>" class="btn-small btn-success-small">
                                                <i class="fas fa-undo-alt"></i> Retour
                                            </a>
                                            <a href="prolonger_emprunt.php?id=<?= $e['id'] ?>" class="btn-small btn-warning-small">
                                                <i class="fas fa-calendar-plus"></i> Prolonger
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-msg">
                            <i class="fas fa-book"></i> Aucun emprunt en cours.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Amendes impayées -->
                <?php if ($amendes_impayees): ?>
                <div class="content-section" style="margin-top: 2rem;">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-coins"></i>
                            Amendes en attente (<?= count($amendes_impayees) ?>)
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($amendes_impayees as $a): ?>
                                <tr>
                                    <td><?= format_date($a['date_creation']) ?></td>
                                    <td><strong><?= number_format($a['montant'], 2) ?> DH</strong></td>
                                    <td><?= safe($a['motif'], '—') ?></td>
                                    <td>
                                        <span class="status-badge status-danger">Impayée</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            <?php endif; ?>

            <div style="text-align:center; margin: 3rem 0;">
                <a href="liste_membres.php" style="color:var(--primary); font-weight:600; text-decoration:none;">
                    <i class="fas fa-arrow-left"></i> Retour à la liste des membres
                </a>
            </div>

        </section>
    </div>

</body>
</html>