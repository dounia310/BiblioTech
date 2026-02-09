<?php
session_start();
require_once 'connexion.php';

// Vérification des droits staff
if (!isset($_SESSION['user_id']) || 
    !in_array($_SESSION['role'], ['bibliothecaire', 'admin', 'super_admin'])) {
    header("Location: login.php");
    exit;
}

$membre_id = (int)($_GET['id'] ?? 0);
if ($membre_id <= 0) {
    header("Location: liste_membres.php?error=ID invalide");
    exit;
}

$error = '';
$membre = null;
$emprunts_en_cours = [];
$reservations_actives = [];
$amendes_impayees = [];

try {
    // 1. Infos du membre
    $stmt = $pdo->prepare("SELECT * FROM membres WHERE id = ?");
    $stmt->execute([$membre_id]);
    $membre = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$membre) {
        header("Location: liste_membres.php?error=Membre non trouvé");
        exit;
    }

    // 2. Emprunts en cours (correction complète avec ta structure)
    $emprunts_stmt = $pdo->prepare("
        SELECT 
            e.id,
            e.date_emprunt,
            e.date_retour_prevue,
            l.titre,
            l.isbn AS code_barre,
            DATEDIFF(CURDATE(), e.date_retour_prevue) AS jours_retard
        FROM emprunts e
        JOIN livres l ON e.exemplaire_id = l.id
        WHERE e.membre_id = ?
          AND e.date_retour_effective IS NULL
        ORDER BY e.date_retour_prevue ASC
    ");
    $emprunts_stmt->execute([$membre_id]);
    $emprunts_en_cours = $emprunts_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 3. Réservations actives
    $reservations_stmt = $pdo->prepare("
        SELECT 
            r.id, 
            r.date_reservation, 
            r.statut, 
            l.titre
        FROM reservations r
        JOIN livres l ON r.livre_id = l.id
        WHERE r.membre_id = ? 
          AND r.statut IN ('en_attente', 'disponible')
        ORDER BY r.date_reservation DESC
    ");
    $reservations_stmt->execute([$membre_id]);
    $reservations_actives = $reservations_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 4. Amendes impayées
    $amendes_stmt = $pdo->prepare("
        SELECT 
            id, 
            montant, 
            date_creation, 
            statut, 
            motif
        FROM amendes
        WHERE membre_id = ? 
          AND statut IN ('impaye', 'partiel')
        ORDER BY date_creation DESC
    ");
    $amendes_stmt->execute([$membre_id]);
    $amendes_impayees = $amendes_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (PDOException $e) {
    $error = "Erreur base de données : " . htmlspecialchars($e->getMessage());
    error_log("Erreur SQL profil membre (ID $membre_id) : " . $e->getMessage());
}

// Fonctions utilitaires
function safe($value, $default = '—') {
    return $value !== null && $value !== '' ? htmlspecialchars($value) : $default;
}

function format_date($date_str, $format = 'd/m/Y', $default = '—') {
    if (empty($date_str) || !strtotime($date_str)) {
        return $default;
    }
    return date($format, strtotime($date_str));
}

$nom_complet = safe(trim(($membre['prenom'] ?? '') . ' ' . ($membre['nom'] ?? '')), 'Membre inconnu');

$retard_class = function($jours) {
    if ($jours <= 0) return 'badge-success';
    if ($jours <= 7)  return 'badge-warning';
    return 'badge-danger';
};
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil – <?= $nom_complet ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #2b848e;
            --primary-dark: #1e6a72;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #254c5b;
            --gray: #6c757d;
            --radius: 8px;
        }
        body { 
            font-family: 'Segoe UI', system-ui, sans-serif; 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: var(--dark); 
            margin: 0; 
            padding: 20px; 
            line-height: 1.6;
            min-height: 100vh;
        }
        .container { 
            max-width: 1000px; 
            margin: 0 auto; 
            background: white;
            border-radius: var(--radius);
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 2.2rem;
        }
        h1 { 
            color: var(--primary); 
            text-align: center; 
            margin-bottom: 2rem; 
            font-size: 2rem;
        }
        .card { 
            background: white; 
            border-radius: var(--radius); 
            padding: 1.8rem; 
            margin-bottom: 2rem; 
            border-left: 5px solid var(--primary);
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }
        .grid-2 { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
            gap: 1.6rem; 
        }
        .info-row { 
            display: flex; 
            align-items: flex-start; 
            gap: 14px; 
        }
        .icon { 
            font-size: 1.4rem; 
            color: var(--primary); 
            min-width: 36px; 
            text-align: center; 
        }
        .label { 
            font-weight: 600; 
            color: #555; 
            min-width: 150px; 
            font-size: 0.98rem; 
        }
        .value { 
            flex: 1; 
            color: #333; 
        }
        .actions { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 12px; 
            margin-top: 2rem; 
            justify-content: center; 
            padding-top: 1.6rem; 
            border-top: 1px solid #e9ecef;
        }
        .btn { 
            padding: 10px 18px; 
            border: none; 
            border-radius: 6px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.96rem;
        }
        .btn:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 5px 15px rgba(0,0,0,0.12); 
        }
        .btn-primary    { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-danger     { background: var(--danger); color: white; }
        .btn-success    { background: var(--success); color: white; }
        .btn-warning    { background: var(--warning); color: #212529; }
        .btn-secondary  { background: var(--gray); color: white; }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 0.8rem; 
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        th, td { 
            padding: 14px 16px; 
            text-align: left; 
            border-bottom: 1px solid #e9ecef; 
        }
        th { 
            background: var(--primary); 
            color: white; 
            font-weight: 600; 
        }
        tr:hover { 
            background: rgba(43,132,142,0.04); 
        }
        .status-badge {
            padding: 5px 13px;
            border-radius: 20px;
            font-size: 0.86rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger  { background: #f8d7da; color: #721c24; }
        .empty-msg {
            color: #6c757d;
            font-style: italic;
            text-align: center;
            padding: 2.5rem 1rem;
            background: #f8f9fa;
            border-radius: var(--radius);
            margin: 1.2rem 0;
        }
        .alert-danger {
            padding: 1.2rem;
            border-radius: var(--radius);
            margin-bottom: 1.8rem;
            text-align: center;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .stat-card {
            background: linear-gradient(135deg, #f9fafb, #f1f3f5);
            border-radius: var(--radius);
            padding: 1.6rem;
            text-align: center;
            border-top: 5px solid var(--primary);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .stat-number {
            font-size: 2.8rem;
            font-weight: 700;
            color: var(--primary);
            margin: 8px 0;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            padding: 12px 24px;
            background: rgba(43,132,142,0.08);
            border-radius: var(--radius);
            transition: all 0.25s;
        }
        .back-link:hover {
            background: rgba(43,132,142,0.15);
            transform: translateX(-6px);
        }
    </style>
</head>
<body>

<div class="container">

    <h1><i class="fas fa-user-circle"></i> Profil de <?= $nom_complet ?></h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($membre): ?>

        <!-- Statistiques rapides -->
        <div class="grid-2" style="margin-bottom: 2.2rem;">
            <div class="stat-card">
                <div class="stat-label">Emprunts en cours</div>
                <div class="stat-number"><?= count($emprunts_en_cours) ?></div>
                <div class="stat-sub">
                    dont <strong><?= count(array_filter($emprunts_en_cours, fn($e) => $e['jours_retard'] > 0)) ?></strong> en retard
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Amendes en attente</div>
                <div class="stat-number"><?= count($amendes_impayees) ?></div>
                <div class="stat-sub">
                    Total : <strong><?= number_format(array_sum(array_column($amendes_impayees, 'montant')), 2) ?> DH</strong>
                </div>
            </div>
        </div>

        <!-- Informations personnelles – affichage propre et sécurisé -->
        <div class="card">
            <h2 style="margin:0 0 1.6rem; font-size:1.35rem; color:var(--primary);">
                <i class="fas fa-info-circle"></i> Informations personnelles
            </h2>
            
            <div class="grid-2">
                <div class="info-row">
                    <div class="icon"><i class="fas fa-id-card"></i></div>
                    <div class="label">N° membre</div>
                    <div class="value"><?= safe($membre['numero_membre']) ?></div>
                </div>
                <div class="info-row">
                    <div class="icon"><i class="fas fa-envelope"></i></div>
                    <div class="label">Email</div>
                    <div class="value"><?= safe($membre['email']) ?></div>
                </div>
                <div class="info-row">
                    <div class="icon"><i class="fas fa-phone"></i></div>
                    <div class="label">Téléphone</div>
                    <div class="value"><?= safe($membre['telephone'], 'Non renseigné') ?></div>
                </div>
                <div class="info-row">
                    <div class="icon"><i class="fas fa-home"></i></div>
                    <div class="label">Adresse</div>
                    <div class="value">
                        <?= safe($membre['adresse']) ?><br>
                        <?= implode(' ', array_filter([
                            safe($membre['code_postal']),
                            safe($membre['ville'])
                        ])) ?><?= safe($membre['code_postal']) || safe($membre['ville']) ? ', ' : '' ?>
                        <?= safe($membre['pays'], 'Maroc') ?>
                    </div>
                </div>
                <div class="info-row">
                    <div class="icon"><i class="fas fa-calendar-day"></i></div>
                    <div class="label">Date de naissance</div>
                    <div class="value"><?= format_date($membre['date_naissance'] ?? '') ?></div>
                </div>
                <div class="info-row">
                    <div class="icon"><i class="fas fa-briefcase"></i></div>
                    <div class="label">Profession</div>
                    <div class="value"><?= safe($membre['profession'], '—') ?></div>
                </div>
                <div class="info-row">
                    <div class="icon"><i class="fas fa-building"></i></div>
                    <div class="label">Institution</div>
                    <div class="value"><?= safe($membre['institution'], '—') ?></div>
                </div>
                <div class="info-row">
                    <div class="icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="label">Date d'inscription</div>
                    <div class="value"><?= format_date($membre['date_inscription'] ?? '') ?></div>
                </div>
                <div class="info-row">
                    <div class="icon"><i class="fas fa-user-check"></i></div>
                    <div class="label">Statut</div>
                    <div class="value">
                        <?php
                        $statut = strtolower(safe($membre['statut'], 'inconnu'));
                        $classe = $statut === 'actif' ? 'badge-success' : ($statut === 'suspendu' ? 'badge-danger' : 'badge-warning');
                        ?>
                        <span class="status-badge <?= $classe ?>"><?= ucfirst($statut) ?></span>
                    </div>
                </div>
            </div>

            <div class="actions">
                <a href="modifier_membre.php?id=<?= $membre_id ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Modifier</a>
                <a href="supprimer_membre.php?id=<?= $membre_id ?>" class="btn btn-danger" onclick="return confirm('Supprimer définitivement ce membre ?')"><i class="fas fa-trash-alt"></i> Supprimer</a>
                <a href="mailto:<?= safe($membre['email']) ?>" class="btn btn-success"><i class="fas fa-envelope"></i> Contacter</a>
                <a href="nouvel_emprunt.php?membre_id=<?= $membre_id ?>" class="btn btn-primary"><i class="fas fa-book-reader"></i> Nouvel emprunt</a>
                <a href="historique_membre.php?id=<?= $membre_id ?>" class="btn btn-secondary"><i class="fas fa-history"></i> Historique</a>
            </div>
        </div>

        <!-- Emprunts en cours -->
        <div class="section">
            <h2 class="section-title"><i class="fas fa-book-open"></i> Emprunts en cours (<?= count($emprunts_en_cours) ?>)</h2>
            <?php if ($emprunts_en_cours): ?>
                <table>
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
                                <td><code><?= safe($e['code_barre']) ?></code></td>
                                <td><?= format_date($e['date_emprunt']) ?></td>
                                <td><?= format_date($e['date_retour_prevue']) ?></td>
                                <td>
                                    <span class="status-badge <?= $retard_class($e['jours_retard']) ?>">
                                        <?= $e['jours_retard'] > 0 ? '<i class="fas fa-clock"></i> ' . $e['jours_retard'] . ' j' : 'À temps' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="retour_livre.php?emprunt_id=<?= $e['id'] ?>" class="btn btn-success btn-sm"><i class="fas fa-undo-alt"></i> Retour</a>
                                    <a href="prolonger_emprunt.php?id=<?= $e['id'] ?>" class="btn btn-warning btn-sm"><i class="fas fa-calendar-plus"></i> Prolonger</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="empty-msg"><i class="fas fa-book"></i> Aucun emprunt en cours.</p>
            <?php endif; ?>
        </div>

        <!-- Réservations et Amendes (à compléter si besoin) -->

        <div style="text-align:center; margin-top:3.5rem;">
            <a href="liste_membres.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Retour à la liste des membres
            </a>
        </div>

    <?php endif; ?>

</div>
</body>
</html>