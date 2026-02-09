<?php
require_once 'connexion.php';
require_once 'header.php';

// Récupérer les livres récemment ajoutés (30 derniers jours)
$query = "
    SELECT 
        l.*,
        a.nom_complet AS auteur,
        c.nom AS categorie_nom,
        (SELECT COUNT(*) FROM exemplaires ex WHERE ex.livre_id = l.id AND ex.statut = 'disponible') AS exemplaires_disponibles,
        l.note_moyenne
    FROM livres l
    LEFT JOIN auteurs a ON l.auteur_id = a.id
    LEFT JOIN livre_categories lc ON l.id = lc.livre_id
    LEFT JOIN categories c ON lc.categorie_id = c.id
    WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)  -- CORRECTION ICI
       OR l.statut_bibliographique = 'nouveaute'             -- ET ICI (si vous voulez utiliser ce statut)
    GROUP BY l.id
    ORDER BY l.created_at DESC                               -- ET ICI
    LIMIT 12
";

$stmt = $pdo->query($query);
$nouveautes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveautés - Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Styles existants - ajoutez ou ajustez selon besoin */
        :root {
            --primary: #2b848e;
            --primary-dark: #1e6a72;
            --secondary: #7ab781;
            --light: #f8f9fa;
            --dark: #254c5b;
            --warning: #ffb300;
        }
        
        .nouveautes-section {
            padding: 3rem 0;
            background: linear-gradient(to bottom, #f8fafc, #e2e8f0);
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 2.5rem;
            color: var(--dark);
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .section-title p {
            color: #64748b;
            font-size: 1.1rem;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 2rem;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        
        .book-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .book-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .badge-nouveau {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--warning);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 2;
        }
        
        .book-cover {
            height: 280px;
            position: relative;
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            overflow: hidden;
        }
        
        .book-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .book-card:hover .book-cover img {
            transform: scale(1.05);
        }
        
        .book-info {
            padding: 1.5rem;
        }
        
        .book-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
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
            margin-bottom: 0.8rem;
        }
        
        .book-rating {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            margin-bottom: 1rem;
        }
        
        .rating-stars {
            color: var(--warning);
        }
        
        .rating-value {
            font-size: 0.9rem;
            color: #64748b;
            margin-left: 0.3rem;
        }
        
        .book-actions {
            display: flex;
            gap: 0.8rem;
        }
        
        .btn-detail, .btn-add {
            flex: 1;
            padding: 0.7rem;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .btn-detail {
            background: #e2e8f0;
            color: var(--dark);
        }
        
        .btn-detail:hover {
            background: var(--primary);
            color: white;
        }
        
        .btn-add {
            background: var(--primary);
            color: white;
        }
        
        .btn-add:hover {
            background: var(--primary-dark);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem;
            color: #64748b;
            grid-column: 1 / -1;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 1.5rem;
                padding: 0 1rem;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 480px) {
            .books-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main>
        <section class="nouveautes-section">
            <div class="section-title">
                <h2>Découvrez les Nouveautés</h2>
                <p>Les derniers livres ajoutés à notre collection. Soyez parmi les premiers à les découvrir !</p>
            </div>
            
            <div class="books-grid">
                <?php if (empty($nouveautes)): ?>
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <h3>Aucune nouveauté pour le moment</h3>
                        <p>Revenez bientôt pour découvrir nos nouveaux arrivages !</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($nouveautes as $livre): ?>
                        <div class="book-card">
                            <div class="badge-nouveau">NOUVEAU</div>
                            <div class="book-cover">
                                <?php if (!empty($livre['couverture_url'])): ?>
                                    <img src="<?= htmlspecialchars($livre['couverture_url']) ?>" 
                                         alt="Couverture : <?= htmlspecialchars($livre['titre']) ?>">
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; justify-content: center; height: 100%;">
                                        <i class="fas fa-book fa-5x" style="color: #94a3b8;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="book-info">
                                <h3 class="book-title"><?= htmlspecialchars($livre['titre']) ?></h3>
                                <div class="book-author">
                                    <?= htmlspecialchars($livre['auteur'] ?? 'Auteur inconnu') ?>  
                                </div>
                                
                                <div class="book-price">
                                    <?= number_format($livre['prix'] ?? 0, 2) ?> DH
                                </div>
                                
                                <div class="book-rating">
                                    <div class="rating-stars">
                                        <?php
                                        $note = $livre['note_moyenne'] ?? 0;
                                        for ($i = 1; $i <= 5; $i++):
                                            if ($i <= floor($note)):
                                                echo '<i class="fas fa-star"></i>';
                                            elseif ($i - 0.5 <= $note):
                                                echo '<i class="fas fa-star-half-alt"></i>';
                                            else:
                                                echo '<i class="far fa-star"></i>';
                                            endif;
                                        endfor;
                                        ?>
                                    </div>
                                    <span class="rating-value"><?= number_format($note, 1) ?></span>
                                </div>
                                
                                <div class="book-actions">
                                    <a href="details_livre.php?id=<?= $livre['id'] ?>" class="btn-detail">
                                        Détails
                                    </a>
                                    <form action="panier.php" method="post" style="flex:1">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="livre_id" value="<?= $livre['id'] ?>">
                                        <button type="submit" class="btn-add">
                                            Ajouter au panier
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
    
    <?php require_once 'footer.php'; ?>
</body>
</html>