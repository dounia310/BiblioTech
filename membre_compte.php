<?php

require_once 'connexion.php';

// DEBUT DU FICHIER - CORRIGÉ
session_start();

require_once 'connexion.php';

// Protection membre uniquement - CORRIGÉ selon votre login.php
if (!isset($_SESSION['user_id']) || ($_SESSION['type'] ?? '') !== 'membre') {
    // Debug pour voir ce qui se passe
    error_log("Redirection login - user_id: " . ($_SESSION['user_id'] ?? 'non défini') . 
              " | type: " . ($_SESSION['type'] ?? 'non défini'));
    header('Location: login.php');
    exit;
}

$membre_id = (int)$_SESSION['user_id'];

// Infos membre + stats clés
$stmt = $pdo->prepare("
    SELECT m.*,
           (SELECT COUNT(*) FROM emprunts e WHERE e.membre_id = m.id AND e.statut = 'en_cours') as emprunts_en_cours,
           (SELECT COUNT(*) FROM reservations r WHERE r.membre_id = m.id AND r.statut IN ('en_attente','disponible')) as reservations_actives,
           (SELECT COALESCE(SUM(a.montant - a.montant_paye), 0) FROM amendes a WHERE a.membre_id = m.id AND a.statut IN ('impaye','partiel')) as amendes_impayees
    FROM membres m 
    WHERE m.id = ?
");
$stmt->execute([$membre_id]);
$membre = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!$membre) {
    session_destroy();
    header('Location: login.php?error=compte_introuvable');
    exit;
}

// Emprunts en cours
$emprunts_stmt = $pdo->prepare("
    SELECT e.*, l.titre, a.nom_complet AS auteur, l.couverture_url,
           ex.code_barre, ex.etat,
           DATEDIFF(CURDATE(), e.date_retour_prevue) as jours_retard
    FROM emprunts e
    JOIN exemplaires ex ON e.exemplaire_id = ex.id
    JOIN livres l ON ex.livre_id = l.id
    LEFT JOIN auteurs a ON l.auteur_id = a.id  -- CORRECTION ICI
    WHERE e.membre_id = ? AND e.statut = 'en_cours'
    ORDER BY e.date_retour_prevue ASC
");
$emprunts_stmt->execute([$membre_id]);
$emprunts = $emprunts_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Réservations actives
$reservations_stmt = $pdo->prepare("
    SELECT r.*, l.titre, a.nom_complet AS auteur
    FROM reservations r
    JOIN livres l ON r.livre_id = l.id
    LEFT JOIN auteurs a ON l.auteur_id = a.id  -- CORRECTION ICI
    WHERE r.membre_id = ? AND r.statut IN ('en_attente','disponible')
    ORDER BY r.date_reservation DESC
");
$reservations_stmt->execute([$membre_id]);
$reservations = $reservations_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Historique récent (5 derniers) - CORRIGÉ
$historique_stmt = $pdo->prepare("
    SELECT e.*, l.titre, a.nom_complet AS auteur
    FROM emprunts e
    JOIN exemplaires ex ON e.exemplaire_id = ex.id
    JOIN livres l ON ex.livre_id = l.id
    LEFT JOIN auteurs a ON l.auteur_id = a.id  -- CORRECTION ICI
    WHERE e.membre_id = ? AND e.statut = 'retourne'
    ORDER BY e.date_retour_effective DESC
    LIMIT 5
");
$historique_stmt->execute([$membre_id]);
$historique = $historique_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Amendes impayées - CORRIGÉ
$amendes_stmt = $pdo->prepare("
    SELECT a.*, l.titre
    FROM amendes a
    LEFT JOIN emprunts e ON a.emprunt_id = e.id
    LEFT JOIN exemplaires ex ON e.exemplaire_id = ex.id
    LEFT JOIN livres l ON ex.livre_id = l.id
    WHERE a.membre_id = ? AND a.statut IN ('impaye','partiel')
    ORDER BY a.date_creation DESC
");
$amendes_stmt->execute([$membre_id]);
$amendes = $amendes_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace – Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #2b848e;
            --primary-dark: #1e6a72;
            --secondary: #7ab781;
            --dark: #254c5b;
            --light: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --radius-lg: 20px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 10px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            --transition: all 0.3s ease;
        }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
            color: var(--dark);
            margin: 0;
            line-height: 1.6;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* Hero membre */
        .hero-member {
            background: linear-gradient(135deg, var(--dark) 0%, var(--primary) 100%);
            border-radius: var(--radius-lg);
            padding: 3.5rem 2.5rem;
            box-shadow: var(--shadow-md);
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
        }

        .hero-avatar {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            object-fit: cover;
            border: 6px solid rgba(255,255,255,0.3);
            box-shadow: 0 12px 40px rgba(0,0,0,0.4);
            transition: var(--transition);
        }

        .hero-avatar:hover {
            transform: scale(1.05);
        }

        .hero-info h1 {
            font-size: 3rem;
            font-weight: 900;
            margin: 0 0 1rem;
            background: linear-gradient(to right, white, var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero-info p {
            font-size: 1.25rem;
            opacity: 0.9;
            margin: 1.2rem 0 2rem;
            background: inherit;                 
            color: white;                        
        }

        .hero-info .badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.6rem 1.4rem;
            border-radius: 9999px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.8rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            border: 1px solid var(--gray-200);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 45px rgba(37,76,91,0.2);
        }

        .stat-icon {
            font-size: 3.2rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .stat-value {
            font-size: 2.8rem;           
            font-weight: 900;
            color: var(--dark);
            margin: 0.5rem 0;
        }

        .stat-label {
            color: #64748b;
            font-size: 1rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2.2rem;
            margin-bottom: 2.8rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
        }

    
        .btn {
            padding: 0.9rem 1.8rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.7rem;
            text-decoration: none;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-3px); }
        .btn-outline { border: 2px solid var(--primary); color: var(--primary); background: transparent; }
        .btn-outline:hover { background: var(--primary); color: white; }

        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1.3rem; text-align: left; border-bottom: 1px solid var(--gray-200); }
        th { background: var(--gray-100); font-weight: 600; color: var(--dark); }
        tr:hover { background: rgba(43,132,142,0.05); }

        .badge {
            padding: 0.55rem 1.1rem;
            border-radius: 9999px;
            font-size: 0.92rem;
            font-weight: 600;
        }

        .badge-success { background: #ecfdf5; color: #065f46; }
        .badge-warning { background: #fffbeb; color: #92400e; }
        .badge-danger  { background: #fef2f2; color: #991b1b; }
        .badge-info    { background: #eff6ff; color: #1e40af; }

        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            color: #64748b;
        }
        .empty-state i { font-size: 5rem; opacity: 0.35; margin-bottom: 1.8rem; }

        @media (max-width: 1024px) {
            .hero-member { grid-template-columns: 1fr; text-align: center; }
            .hero-avatar { margin: 0 auto 2rem; }
        }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
            .card-header { flex-direction: column; gap: 1rem; text-align: center; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="dashboard-container">

    <!-- Hero membre -->
    <div class="hero-member">
        <div class="hero-info">
            <h1>Bonjour, <?= htmlspecialchars($membre['prenom'] ?? 'Membre') ?> !</h1>
            <p style="font-size:1.25rem; opacity:0.9; margin:1.2rem 0 2rem;">
                Bienvenue dans votre espace personnel Bibliotech
            </p>
            <div class="badge" style="font-size:1.15rem; margin-bottom:2rem;">
                <i class="fas fa-id-card"></i> 
                <?= htmlspecialchars($membre['numero_membre'] ?? '—') ?>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book-reader"></i></div>
                    <div class="stat-value"><?= $membre['emprunts_en_cours'] ?></div>
                    <div class="stat-label">Emprunts en cours</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-bookmark"></i></div>
                    <div class="stat-value"><?= $membre['reservations_actives'] ?></div>
                    <div class="stat-label">Réservations actives</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-coins"></i></div>
                    <div class="stat-value"><?= number_format($membre['amendes_impayees'], 2) ?> DH</div>
                    <div class="stat-label">Amendes en attente</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-value" style="font-size: 2.8rem;">
                        <?= date('d/m/Y', strtotime($membre['date_expiration'] ?? 'now')) ?>
                    </div>
                    <div class="stat-label">Abonnement valide jusqu'au</div>
                </div>
            </div>
        </div>

        <div style="text-align:center;">
            <?php if (!empty($membre['photo_url'])): ?>
                <img src="<?= htmlspecialchars($membre['photo_url']) ?>" 
                     alt="Photo de profil" 
                     class="hero-avatar"
                     onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?= urlencode($membre['prenom'].' '.$membre['nom']) ?>&background=2b848e&color=fff&size=200';">
            <?php else: ?>
                <div class="hero-avatar">
                    <i class="fas fa-user"></i>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Actions rapides -->
    <div class="card" style="margin-bottom:3rem;">
        <div class="card-header">
            <h2>Actions rapides</h2>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1.8rem;">
            <a href="catalogue.php" class="btn btn-primary">
                <i class="fas fa-book-open fa-lg"></i> Explorer le catalogue
            </a>
            <a href="mes_emprunts.php" class="btn btn-primary">
                <i class="fas fa-book-reader fa-lg"></i> Mes emprunts
            </a>
            <a href="mes_reservations.php" class="btn btn-primary">
                <i class="fas fa-bookmark fa-lg"></i> Mes réservations
            </a>
            <a href="modifier_profil.php" class="btn btn-primary">
                <i class="fas fa-user-edit fa-lg"></i> Modifier mon profil
            </a>
        </div>
    </div>

    <!-- Emprunts en cours -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-book-reader"></i> Emprunts en cours</h2>
            <a href="mes_emprunts.php" class="btn btn-outline">Voir tout</a>
        </div>
        <?php if (empty($emprunts)): ?>
            <div class="empty-state">
                <i class="fas fa-book-open fa-5x"></i>
                <h3>Aucun emprunt en cours</h3>
                <p>Explorez le catalogue pour emprunter vos prochains livres !</p>
                <a href="catalogue.php" class="btn btn-primary" style="margin-top:1.5rem;">
                    Découvrir le catalogue
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Livre</th>
                            <th>Emprunté le</th>
                            <th>Retour prévu</th>
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emprunts as $e): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($e['titre']) ?></strong><br>
                                    <small style="color:#64748b;"><?= htmlspecialchars($e['auteur']) ?></small>
                                </td>
                                <td><?= date('d/m/Y', strtotime($e['date_emprunt'])) ?></td>
                                <td>
                                    <?= date('d/m/Y', strtotime($e['date_retour_prevue'])) ?>
                                    <?php if ($e['jours_retard'] > 0): ?>
                                        <br><span class="badge badge-danger">Retard : <?= $e['jours_retard'] ?> j</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $e['jours_retard'] > 0 ? 'badge-danger' : 'badge-success' ?>">
                                        <?= $e['jours_retard'] > 0 ? 'En retard' : 'En cours' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($e['prolongations'] < 2): ?>
                                        <a href="prolonger_emprunt.php?id=<?= $e['id'] ?>" class="btn btn-outline" style="padding:0.7rem 1.2rem;">
                                            <i class="fas fa-redo"></i> Prolonger
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Réservations -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-calendar-check"></i> Réservations actives</h2>
            <a href="mes_reservations.php" class="btn btn-outline">Voir tout</a>
        </div>
        <?php if (empty($reservations)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-days fa-5x"></i>
                <h3>Aucune réservation active</h3>
                <p>Réservez dès maintenant vos prochaines lectures !</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Livre</th>
                            <th>Réservé le</th>
                            <th>Statut</th>
                            <th>Position</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reservations as $r): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($r['titre']) ?></strong><br>
                                    <small style="color:#64748b;"><?= htmlspecialchars($r['auteur']) ?></small>
                                </td>
                                <td><?= date('d/m/Y', strtotime($r['date_reservation'])) ?></td>
                                <td>
                                    <span class="badge <?= $r['statut'] === 'disponible' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= $r['statut'] === 'disponible' ? 'Disponible' : 'En attente' ?>
                                    </span>
                                </td>
                                <td><?= $r['position'] ? '#' . $r['position'] : '—' ?></td>
                                <td>
                                    <?php if ($r['statut'] === 'disponible'): ?>
                                        <a href="recuperer_reservation.php?id=<?= $r['id'] ?>" class="btn btn-primary" style="padding:0.7rem 1.2rem;">
                                            <i class="fas fa-check"></i> Récupérer
                                        </a>
                                    <?php else: ?>
                                        <a href="annuler_reservation.php?id=<?= $r['id'] ?>" class="btn btn-outline" style="padding:0.7rem 1.2rem; color:#dc3545; border-color:#dc3545;">
                                            <i class="fas fa-times"></i> Annuler
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Historique récent -->
    <?php if (!empty($historique)): ?>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-history"></i> Historique récent</h2>
            <a href="historique_emprunts.php" class="btn btn-outline">Voir tout</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Livre</th>
                        <th>Emprunté le</th>
                        <th>Rendu le</th>
                        <th>Durée</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historique as $h): 
                        $jours = floor((strtotime($h['date_retour_effective']) - strtotime($h['date_emprunt'])) / 86400);
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($h['titre']) ?></strong><br>
                            <small style="color:#64748b;"><?= htmlspecialchars($h['auteur']) ?></small>
                        </td>
                        <td><?= date('d/m/Y', strtotime($h['date_emprunt'])) ?></td>
                        <td><?= date('d/m/Y', strtotime($h['date_retour_effective'])) ?></td>
                        <td><?= $jours ?> jour<?= $jours > 1 ? 's' : '' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Amendes -->
    <?php if (!empty($amendes)): ?>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-coins"></i> Amendes à régler</h2>
            <a href="mes_amendes.php" class="btn btn-outline">Voir tout</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Motif</th>
                        <th>Montant total</th>
                        <th>Payé</th>
                        <th>Reste dû</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($amendes as $a): 
                        $reste = $a['montant'] - $a['montant_paye'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($a['description'] ?? 'Amende') ?></td>
                        <td><?= number_format($a['montant'], 2) ?> DH</td>
                        <td><?= number_format($a['montant_paye'], 2) ?> DH</td>
                        <td style="font-weight:bold; color:<?= $reste > 0 ? '#dc3545' : '#10b981' ?>">
                            <?= number_format($reste, 2) ?> DH
                        </td>
                        <td><?= date('d/m/Y', strtotime($a['date_creation'])) ?></td>
                        <td>
                            <?php if ($reste > 0): ?>
                                <a href="payer_amende.php?id=<?= $a['id'] ?>" 
                                   class="btn btn-primary" style="padding:0.7rem 1.2rem;">
                                    <i class="fas fa-credit-card"></i> Payer
                                </a>
                            <?php else: ?>
                                <span class="badge badge-success">Payée</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once 'footer.php'; ?>
</body>
</html>