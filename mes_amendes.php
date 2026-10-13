<?php
require_once 'connexion.php';
session_start();

// Protection membre
if (!isset($_SESSION['user_id']) || ($_SESSION['type'] ?? null) !== 'membre') {
    header('Location: login.php');
    exit;
}

$membre_id = (int)$_SESSION['user_id'];

// Notifications (comme dans toutes les pages membre)
$stmt_notif = $pdo->prepare("
    SELECT id, type, titre, message, lien, lue, created_at 
    FROM notifications 
    WHERE (user_id = ? OR user_id IS NULL) 
    ORDER BY created_at DESC 
    LIMIT 8
");
$stmt_notif->execute([$membre_id]);
$notifications = $stmt_notif->fetchAll(PDO::FETCH_ASSOC) ?? [];

// Amendes du membre
$stmt = $pdo->prepare("
    SELECT a.*,
           l.titre
    FROM amendes a
    LEFT JOIN emprunts e ON a.emprunt_id = e.id
    LEFT JOIN exemplaires ex ON e.exemplaire_id = ex.id
    LEFT JOIN livres l ON ex.livre_id = l.id
    WHERE a.membre_id = ?
    ORDER BY a.date_creation DESC
");
$stmt->execute([$membre_id]);
$amendes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_restant = 0.0;
foreach ($amendes as $a) {
    $total_restant += max(0, (float)$a['montant'] - (float)$a['montant_paye']);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Amendes - Bibliotech</title>
    
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

        .total-restant {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            text-align: center;
            font-size: 1.4rem;
            font-weight: 700;
            border-left: 5px solid var(--danger);
        }

        .amende-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 5px solid var(--warning);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .amende-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }

        .amende-card.paye {
            border-left-color: var(--success);
            background: #f0fdf4;
        }

        .amende-content {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .amende-image {
            width: 110px;
            height: 160px;
            object-fit: cover;
            border-radius: 8px;
            background: var(--gray-200);
            flex-shrink: 0;
        }

        .amende-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .amende-details h3 {
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

        .amount-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
            padding: 1rem;
            background: var(--gray-100);
            border-radius: var(--radius);
        }

        .amount-item strong {
            display: block;
            color: #374151;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }

        .amount-item .reste {
            color: var(--danger);
            font-weight: 700;
            font-size: 1.1rem;
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

        .btn-primary   { background: var(--primary);   color: white; }
        .btn-success   { background: var(--success);   color: white; }
        .btn-warning   { background: var(--warning);   color: white; }

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

        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
        }

        .empty-state i { font-size: 5rem; color: #d1d5db; margin-bottom: 1.5rem; }

        @media (max-width: 768px) {
            .amende-content { flex-direction: column; }
            .amende-image { width: 100%; height: 220px; }
            .actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<div class="container">
    <h1><i class="fas fa-coins"></i> Mes Amendes</h1>

    <div class="total-restant">
        Total restant à payer : <?= number_format($total_restant, 2) ?> DH
    </div>

    <?php if (empty($amendes)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <h2>Aucune amende en cours</h2>
            <p>Vos emprunts sont en règle. Continuez à profiter de la bibliothèque !</p>
            <a href="catalogue.php" class="btn btn-primary" style="margin-top:1.5rem;">
                <i class="fas fa-book"></i> Explorer le catalogue
            </a>
        </div>
    <?php else: ?>

        <?php foreach ($amendes as $a): 
            $montant = (float)$a['montant'];
            $paye = (float)$a['montant_paye'];
            $reste = max(0, $montant - $paye);
            $est_paye = $a['statut'] === 'paye';
        ?>

            <div class="amende-card <?= $est_paye ? 'paye' : '' ?>">
                <div class="amende-content">

                    <div class="amende-details">
                        <h3>Amende du <?= date('d/m/Y', strtotime($a['date_creation'])) ?></h3>

                        <div class="meta">
                            <span><i class="fas fa-tag"></i> Type : <?= htmlspecialchars($a['type']) ?></span>
                            <?php if ($a['titre']): ?>
                                <span><i class="fas fa-book"></i> Livre : <?= htmlspecialchars($a['titre']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="amount-grid">
                            <div class="amount-item">
                                <strong>Montant initial</strong>
                                <?= number_format($montant, 2) ?> DH
                            </div>
                            <div class="amount-item">
                                <strong>Déjà payé</strong>
                                <?= number_format($paye, 2) ?> DH
                            </div>
                            <div class="amount-item">
                                <strong>Reste à payer</strong>
                                <span class="reste"><?= number_format($reste, 2) ?> DH</span>
                            </div>
                        </div>

                        <div style="margin:1rem 0;">
                            <span class="badge <?= $est_paye ? 'badge-success' : 'badge-danger' ?>">
                                <i class="fas fa-info-circle"></i> 
                                Statut : <?= ucfirst($a['statut']) ?>
                            </span>
                        </div>

                        <div class="actions">
                            <?php if ($reste > 0 && in_array($a['statut'], ['impaye','partiel'])): ?>
                                <a href="payer_amende.php?id=<?= (int)$a['id'] ?>" class="btn btn-primary">
                                    <i class="fas fa-money-bill-wave"></i> Payer maintenant
                                </a>
                            <?php else: ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-check"></i> Amende réglée
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php endforeach; ?>

    <?php endif; ?>

    <a href="dashboard_membre.php" class="back-link" style="margin-top:2rem; display:block; text-align:center;">
        <i class="fas fa-arrow-left"></i> Retour à mon espace membre
    </a>
</div>

</body>
</html>