<?php
require_once 'connexion.php';
require_once 'header.php';

// Récupérer les livres en promotion (exemple : popularité élevée + prix > 0)
$promotions = $pdo->query("
    SELECT l.id, l.titre, a.nom_complet AS auteur, l.couverture_url, l.prix, 
           l.note_moyenne, l.created_at,
           (SELECT COUNT(*) FROM exemplaires e WHERE e.livre_id = l.id AND e.statut = 'disponible') as disponible
    FROM livres l
    LEFT JOIN auteurs a ON l.auteur_id = a.id
    WHERE l.popularite > 30 AND l.prix > 0
    ORDER BY RAND()
    LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC) ?? [];

$code_promo = "BIENVENUE15";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotions – Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --primary-dark: #152a45;
            --accent: #e8b86d;
            --accent-hover: #d4a55c;
            --success: #10b981;
            --error: #ef4444;
            --dark: #0f172a;
            --light: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --warning: #ffb300;
            --radius-md: 16px;
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --transition: 0.3s ease;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--gray-50); 
            color: var(--dark); 
        }

        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
            padding: 0 24px; 
        }

        .promo-banner {
            background: linear-gradient(135deg, var(--accent), #ff8e8e);
            color: white;
            padding: 4rem 2rem;
            border-radius: var(--radius-md);
            margin: 2rem 0;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .promo-banner h1 { font-size: 2.8rem; margin-bottom: 1rem; }
        .promo-code {
            background: white;
            color: var(--accent);
            padding: 12px 32px;
            border-radius: 50px;
            font-size: 1.8rem;
            font-weight: 700;
            display: inline-block;
            margin: 1.5rem 0;
            cursor: pointer;
        }

        .page-title { 
            font-family: 'Playfair Display', serif;
            font-size: 2.8rem; 
            text-align: center; 
            margin: 2rem 0 3rem; 
            color: var(--dark);
        }

        /* ===> Même grille et cartes que catalogue.php <=== */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 4rem;
        }

        .book-card {
            background: white;
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all var(--transition);
            position: relative;
        }

        .book-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent);
        }

        .discount-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--accent);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 700;
            z-index: 2;
            box-shadow: var(--shadow-md);
        }

        .book-cover {
            height: 320px;
            background: linear-gradient(135deg, var(--gray-100), var(--gray-200));
            overflow: hidden;
        }

        .book-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .book-card:hover .book-cover img {
            transform: scale(1.06);
        }

        .no-cover {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-400);
            font-size: 5rem;
        }

        .book-info {
            padding: 1.5rem;
        }

        .book-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--dark);
            line-height: 1.3;
        }

        .book-author {
            color: var(--primary);
            font-size: 0.95rem;
            margin-bottom: 0.8rem;
        }

        .book-price {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--dark);
            margin: 0.8rem 0;
        }

        .old-price {
            font-size: 1rem;
            color: var(--gray-500);
            text-decoration: line-through;
            margin-right: 10px;
        }

        .book-rating {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 1.2rem;
        }

        .rating-stars { color: var(--accent); }

        .book-actions {
            display: flex;
            gap: 1rem;
        }

        .btn-detail, .btn-add {
            flex: 1;
            padding: 0.8rem;
            border-radius: 10px;
            font-weight: 600;
            text-align: center;
            transition: all var(--transition);
            text-decoration: none;
        }

        .btn-detail {
            background: var(--gray-200);
            color: var(--dark);
        }

        .btn-detail:hover { background: var(--primary); color: white; }

        .btn-add {
            background: var(--primary);
            color: white;
        }

        .btn-add:hover { background: var(--primary-dark); }

        .empty-state {
            text-align: center;
            padding: 6rem 2rem;
            grid-column: 1 / -1;
            color: var(--gray-500);
        }

        .empty-state i { font-size: 5rem; color: var(--gray-300); margin-bottom: 1.5rem; }

        @media (max-width: 768px) {
            .books-grid { grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
            .promo-banner { padding: 3rem 1.5rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="promo-banner">
        <h1>Profitez de -15% sur toute la sélection !</h1>
        <p>Code promo pour votre première commande</p>
        <div class="promo-code" onclick="navigator.clipboard.writeText('<?= $code_promo ?>'); alert('Code copié !')">
            <?= $code_promo ?>
        </div>
        <p>Valable sur tous les livres – Offre limitée</p>
    </div>

    <h1 class="page-title">Livres en promotion</h1>

    <?php if (empty($promotions)): ?>
        <div class="empty-state">
            <i class="fas fa-tag"></i>
            <h3>Aucune promotion en cours</h3>
            <p>Revenez bientôt pour découvrir nos offres spéciales</p>
        </div>
    <?php else: ?>
        <div class="books-grid">
            <?php foreach ($promotions as $livre): 
                $prix_original = $livre['prix'];
                $prix_promo = round($prix_original * 0.85, 2);
                $economie = $prix_original - $prix_promo;
            ?>
                <div class="book-card">
                    <div class="discount-badge">-15%</div>

                    <div class="book-cover">
                        <?php if (!empty($livre['couverture_url'])): ?>
                            <img src="<?= htmlspecialchars($livre['couverture_url']) ?>" 
                                 alt="<?= htmlspecialchars($livre['titre']) ?>">
                        <?php else: ?>
                            <div class="no-cover">
                                <i class="fas fa-book"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="book-info">
                        <h3 class="book-title"><?= htmlspecialchars($livre['titre']) ?></h3>
                        <div class="book-author"><?= htmlspecialchars($livre['auteur'] ?? 'Auteur inconnu') ?></div>

                        <div class="book-price">
                            <span class="old-price"><?= number_format($prix_original, 2) ?> DH</span>
                            <?= number_format($prix_promo, 2) ?> DH
                        </div>

                        <div class="book-rating">
                            <div class="rating-stars">
                                <?php
                                $note = $livre['note_moyenne'] ?? 0;
                                for ($i = 1; $i <= 5; $i++):
                                    if ($i <= floor($note)) echo '<i class="fas fa-star"></i>';
                                    elseif ($i - 0.5 <= $note) echo '<i class="fas fa-star-half-alt"></i>';
                                    else echo '<i class="far fa-star"></i>';
                                endfor;
                                ?>
                            </div>
                            <span class="rating-value"><?= number_format($note, 1) ?></span>
                        </div>

                        <div class="book-actions">
                            <a href="details_livre.php?id=<?= $livre['id'] ?>" class="btn-detail">
                                Détails
                            </a>
                            <?php if ($livre['disponible'] > 0): ?>
                                <form action="panier.php" method="post" style="flex:1">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="livre_id" value="<?= $livre['id'] ?>">
                                    <button type="submit" class="btn-add">
                                        Ajouter au panier
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn-add" disabled>Indisponible</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="text-align:center; margin: 4rem 0; padding: 2rem; background: var(--gray-100); border-radius: var(--radius-md);">
        <h3 style="margin-bottom: 1rem;">Profitez de l’offre spéciale :</h3>
        <p style="font-size: 1.3rem; font-weight: 600; color: var(--primary);">
            Achetez un livre → obtenez 1 emprunt gratuit (au choix parmi les livres disponibles)
        </p>
        <p style="margin-top: 1rem; color: var(--gray-600);">
            Valable sur toute commande avec au moins 1 achat. Offre cumulable avec le code <strong><?= $code_promo ?></strong>.
        </p>
    </div>
</div>

<?php require_once 'footer.php'; ?>
</body>
</html>