<?php
// mes_emprunts.php - Page des emprunts en cours (corrigée 2025)
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=mes_emprunts.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupérer les emprunts
try {
    $stmt = $pdo->prepare("
        SELECT 
            e.id,
            e.date_emprunt,
            e.date_retour_prevue,
            e.date_retour_effective,
            e.prolongations,
            e.prolongation_max,
            e.amende_cumulee,
            e.notes,
            e.statut,
            l.titre,
            l.prix,
            l.couverture_url,
            l.id AS livre_id
            -- Si vous avez une table auteurs et un champ auteur_id dans livres, décommentez :
            -- , a.nom_complet AS auteur
        FROM emprunts e
        JOIN exemplaires ex ON e.exemplaire_id = ex.id
        JOIN livres l ON ex.livre_id = l.id
        -- JOIN auteurs a ON l.auteur_id = a.id   ← décommentez seulement si cette jointure existe
        WHERE e.membre_id = ?
        ORDER BY 
            CASE 
                WHEN e.statut = 'en_cours' AND e.date_retour_prevue < CURDATE() THEN 1
                WHEN e.statut = 'en_cours' THEN 2
                ELSE 3
            END,
            e.date_emprunt DESC
    ");
    
    $stmt->execute([$user_id]);
    $emprunts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erreur SQL : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Emprunts - Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --accent: #e8b86d;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-600: #475569;
            --light: #ffffff;
            --radius: 10px;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
            color: #1f2937;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }

        .container { max-width: 1100px; margin: 0 auto; }

        h1 {
            color: var(--primary);
            font-size: 2.2rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title {
            color: var(--primary);
            font-size: 1.6rem;
            margin: 2.5rem 0 1.2rem;
            padding-bottom: 0.6rem;
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .emprunt-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 5px solid var(--info);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .emprunt-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }

        .emprunt-card.retard {
            border-left-color: var(--danger);
            background: #fef2f2;
        }

        .emprunt-card.termine {
            border-left-color: var(--success);
            background: #f0fdf4;
        }

        .emprunt-content {
            display: flex;
            gap: 1.5rem;
        }

        .emprunt-image {
            width: 110px;
            height: 160px;
            object-fit: cover;
            border-radius: 8px;
            background: var(--gray-200);
            flex-shrink: 0;
        }

        .emprunt-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .emprunt-details h3 {
            margin: 0 0 0.6rem;
            color: var(--primary);
            font-size: 1.25rem;
        }

        .meta {
            color: #4b5563;
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }

        .meta span {
            margin-right: 1.2rem;
        }

        .date-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
            padding: 1rem;
            background: var(--gray-100);
            border-radius: var(--radius);
        }

        .date-item strong {
            display: block;
            color: #374151;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }

        .actions {
            margin-top: 1.2rem;
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-warning  { background: var(--warning); color: white; }
        .btn-success  { background: var(--success); color: white; }
        .btn-info     { background: var(--info);    color: white; }
        .btn-danger   { background: var(--danger);  color: white; }

        .btn:hover {
            opacity: 0.92;
            transform: translateY(-1px);
        }

        .badge {
            padding: 0.35rem 0.9rem;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .badge-danger   { background: #fee2e2; color: #991b1b; }
        .badge-success  { background: #d1fae5; color: #065f46; }
        .badge-warning  { background: #fef3c7; color: #92400e; }
        .badge-info     { background: #dbeafe; color: #1e40af; }

        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
        }

        .empty-state i { font-size: 5rem; color: #d1d5db; margin-bottom: 1.5rem; }

        @media (max-width: 768px) {
            .emprunt-content { flex-direction: column; }
            .emprunt-image { width: 100%; height: 220px; }
            .actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<div class="container">
    <h1><i class="fas fa-book-open"></i> Mes Emprunts</h1>

    <?php if (empty($emprunts)): ?>
        <div class="empty-state">
            <i class="fas fa-book-reader"></i>
            <h2>Vous n'avez aucun emprunt en cours</h2>
            <p>Explorez notre catalogue pour emprunter de nouveaux livres</p>
            <a href="catalogue.php" class="btn btn-success" style="margin-top:1.5rem;">
                <i class="fas fa-book"></i> Voir le catalogue
            </a>
        </div>
    <?php else: ?>

        <?php
        // Filtres corrigés avec les vrais statuts de votre table
        $en_cours = array_filter($emprunts, fn($e) => $e['statut'] === 'en_cours');
        $retournes = array_filter($emprunts, fn($e) => $e['statut'] === 'retourne');
        $en_retard = array_filter($en_cours, fn($e) => $e['date_retour_prevue'] < date('Y-m-d'));
        $en_cours_ok = array_diff_key($en_cours, $en_retard);
        ?>

        <!-- Emprunts en retard -->
        <?php if (!empty($en_retard)): ?>
            <h2 class="section-title">
                <i class="fas fa-exclamation-triangle" style="color:var(--danger)"></i>
                Emprunts en retard (<?= count($en_retard) ?>)
            </h2>
            <?php foreach ($en_retard as $e): ?>
                <div class="emprunt-card retard">
                    <div class="emprunt-content">
                        <div class="emprunt-image">
                            <?php if ($e['couverture_url']): ?>
                                <img src="<?= htmlspecialchars($e['couverture_url']) ?>" alt="<?= htmlspecialchars($e['titre']) ?>">
                            <?php else: ?>
                                <i class="fas fa-book" style="font-size:4rem;color:#9ca3af;"></i>
                            <?php endif; ?>
                        </div>

                        <div class="emprunt-details">
                            <h3><?= htmlspecialchars($e['titre']) ?></h3>

                            <div class="meta">
                                <span><i class="fas fa-calendar-alt"></i> Emprunté le <?= date('d/m/Y', strtotime($e['date_emprunt'])) ?></span>
                            </div>

                            <div class="date-grid">
                                <div class="date-item">
                                    <strong>Retour prévu</strong>
                                    <div style="color:var(--danger);font-weight:600;">
                                        <?= date('d/m/Y', strtotime($e['date_retour_prevue'])) ?>
                                    </div>
                                </div>
                            </div>

                            <div style="margin:1rem 0;">
                                <span class="badge badge-danger">
                                    <i class="fas fa-clock"></i> En retard de <?= abs(floor((strtotime($e['date_retour_prevue']) - time()) / 86400)) ?> jours
                                </span>
                            </div>

                            <?php if ($e['amende_cumulee'] > 0): ?>
                                <div style="margin:0.8rem 0;">
                                    <span class="badge badge-danger">
                                        Amende : <?= number_format($e['amende_cumulee'], 2) ?> DH
                                    </span>
                                </div>
                            <?php endif; ?>

                            <div class="actions">
                                <?php if ($e['prolongations'] < $e['prolongation_max']): ?>
                                    <a href="prolonger_emprunt.php?id=<?= $e['id'] ?>" class="btn btn-warning">
                                        <i class="fas fa-redo"></i> Prolonger
                                    </a>
                                <?php endif; ?>

                                <a href="retour_livre.php?id=<?= $e['id'] ?>" class="btn btn-success">
                                    <i class="fas fa-undo"></i> Retourner
                                </a>

                                <a href="details_livre.php?id=<?= $e['livre_id'] ?>" class="btn btn-info">
                                    <i class="fas fa-eye"></i> Détails
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Emprunts en cours normaux -->
        <?php if (!empty($en_cours_ok)): ?>
            <h2 class="section-title">
                <i class="fas fa-bookmark" style="color:var(--info)"></i>
                Emprunts en cours (<?= count($en_cours_ok) ?>)
            </h2>
            <?php foreach ($en_cours_ok as $e): ?>
                <div class="emprunt-card">
                    <div class="emprunt-content">
                        <div class="emprunt-image">
                            <?php if ($e['couverture_url']): ?>
                                <img src="<?= htmlspecialchars($e['couverture_url']) ?>" alt="<?= htmlspecialchars($e['titre']) ?>">
                            <?php else: ?>
                                <i class="fas fa-book" style="font-size:4rem;color:#9ca3af;"></i>
                            <?php endif; ?>
                        </div>

                        <div class="emprunt-details">
                            <h3><?= htmlspecialchars($e['titre']) ?></h3>

                            <div class="meta">
                                <span><i class="fas fa-calendar-alt"></i> Emprunté le <?= date('d/m/Y', strtotime($e['date_emprunt'])) ?></span>
                            </div>

                            <div class="date-grid">
                                <div class="date-item">
                                    <strong>Retour prévu</strong>
                                    <?= date('d/m/Y', strtotime($e['date_retour_prevue'])) ?>
                                </div>
                            </div>

                            <div style="margin:1rem 0;">
                                <span class="badge badge-info">
                                    <i class="fas fa-clock"></i> 
                                    <?= max(0, floor((strtotime($e['date_retour_prevue']) - time()) / 86400)) ?> jours restants
                                </span>
                            </div>

                            <?php if ($e['prolongations'] > 0): ?>
                                <div style="margin:0.8rem 0;">
                                    <span class="badge badge-warning">
                                        Prolongations : <?= $e['prolongations'] ?> / <?= $e['prolongation_max'] ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <div class="actions">
                                <?php if ($e['prolongations'] < $e['prolongation_max']): ?>
                                    <a href="prolonger_emprunt.php?id=<?= $e['id'] ?>" class="btn btn-warning">
                                        <i class="fas fa-redo"></i> Prolonger
                                    </a>
                                <?php endif; ?>

                                <a href="retour_livre.php?id=<?= $e['id'] ?>" class="btn btn-success">
                                    <i class="fas fa-undo"></i> Retourner
                                </a>

                                <a href="details_livre.php?id=<?= $e['livre_id'] ?>" class="btn btn-info">
                                    <i class="fas fa-eye"></i> Détails
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Emprunts retournés -->
        <?php if (!empty($retournes)): ?>
            <h2 class="section-title">
                <i class="fas fa-check-circle" style="color:var(--success)"></i>
                Emprunts retournés (<?= count($retournes) ?>)
            </h2>
            <?php foreach ($retournes as $e): ?>
                <div class="emprunt-card termine">
                    <div class="emprunt-content">
                        <div class="emprunt-image">
                            <?php if ($e['couverture_url']): ?>
                                <img src="<?= htmlspecialchars($e['couverture_url']) ?>" alt="<?= htmlspecialchars($e['titre']) ?>">
                            <?php else: ?>
                                <i class="fas fa-book" style="font-size:4rem;color:#9ca3af;"></i>
                            <?php endif; ?>
                        </div>

                        <div class="emprunt-details">
                            <h3><?= htmlspecialchars($e['titre']) ?></h3>

                            <div class="meta">
                                <span><i class="fas fa-calendar-check"></i> Retourné le <?= date('d/m/Y', strtotime($e['date_retour_effective'])) ?></span>
                            </div>

                            <?php if ($e['amende_cumulee'] > 0): ?>
                                <div style="margin:1rem 0;">
                                    <span class="badge badge-warning">
                                        Amende réglée : <?= number_format($e['amende_cumulee'], 2) ?> DH
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($e['notes'])): ?>
                                <div style="margin:1rem 0; padding:0.8rem; background:#f3f4f6; border-radius:6px;">
                                    <strong>Note :</strong> <?= htmlspecialchars($e['notes']) ?>
                                </div>
                            <?php endif; ?>

                            <div class="actions">
                                <a href="details_livre.php?id=<?= $e['livre_id'] ?>" class="btn btn-info">
                                    <i class="fas fa-eye"></i> Voir le livre
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    <?php endif; ?>
</div>

</body>
</html>