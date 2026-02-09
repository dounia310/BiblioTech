<?php
require_once 'header.php';

$actualites = $pdo->query("
    SELECT * FROM nouvelles 
    WHERE statut = 'publie' 
    ORDER BY date_publication DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualités - Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
      .actualites-container { max-width: 1200px; margin: 40px auto; padding: 0 20px; } .actualites-title { color: var(--primary); margin-bottom: 40px; font-size: 2.5rem; } .actualites-grid { display: grid; gap: 30px; } .actualite-article { background: white; border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow-md); transition: transform 0.3s ease; } .actualite-article:hover { transform: translateY(-5px); } .actualite-image { height: 250px; overflow: hidden; } .actualite-image img { width: 100%; height: 100%; object-fit: cover; } .actualite-content { padding: 25px; } .actualite-date { color: var(--primary); font-weight: 600; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; } .actualite-type { background: var(--secondary); color: white; padding: 2px 10px; border-radius: 20px; font-size: 0.8rem; margin-left: 10px; } .actualite-titre { font-size: 1.5rem; margin-bottom: 15px; color: var(--dark); line-height: 1.4; } .actualite-description { color: var(--gray-700); line-height: 1.6; margin-bottom: 20px; } .no-actualites { text-align: center; padding: 60px 40px; background: var(--gray-100); border-radius: var(--radius); color: var(--gray-500); font-size: 1.1rem; } .filters-section { background: var(--gray-100); padding: 20px; border-radius: var(--radius); margin-bottom: 30px; } .filters-title { margin-bottom: 15px; color: var(--gray-700); font-weight: 600; } .filter-buttons { display: flex; gap: 10px; flex-wrap: wrap; } .filter-btn { background: white; border: 1px solid var(--gray-300); padding: 8px 16px; border-radius: 20px; cursor: pointer; transition: all 0.3s ease; font-size: 0.9rem; } .filter-btn:hover { background: var(--primary); color: white; border-color: var(--primary); } .filter-btn.active { background: var(--primary); color: white; border-color: var(--primary); } .read-more { color: var(--primary); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; margin-top: 15px; } @media (max-width: 768px) { .actualites-title { font-size: 2rem; } .actualite-image { height: 200px; } .filter-buttons { justify-content: center; } } @media (max-width: 480px) { .actualites-title { font-size: 1.8rem; } .actualite-article { margin-bottom: 20px; } .filter-btn { padding: 6px 12px; font-size: 0.9rem; } }</style>
    </style>
</head>
<body>

<div class="actualites-container">
    <h1 class="actualites-title">Actualités de la bibliothèque</h1>
    
    <!-- Section Filtres -->
    <div class="filters-section">
        <h3 class="filters-title">Filtrer par catégorie :</h3>
        <div class="filter-buttons">
            <button class="filter-btn active" data-filter="all">Toutes</button>
            <button class="filter-btn" data-filter="evenement">Événements</button>
            <button class="filter-btn" data-filter="nouveaute">Nouveautés</button>
            <button class="filter-btn" data-filter="promotion">Promotions</button>
            <button class="filter-btn" data-filter="annonce">Annonces</button>
        </div>
    </div>

    <?php if (empty($actualites)): ?>
        <div class="no-actualites">
            <i class="fas fa-newspaper" style="font-size: 3rem; margin-bottom: 20px; color: var(--gray-300);"></i>
            <p>Aucune actualité pour le moment.</p>
            <p style="margin-top: 10px; font-size: 0.9rem;">Revenez bientôt pour découvrir nos nouvelles publications.</p>
        </div>
    <?php else: ?>
        <div class="actualites-grid">
            <?php foreach ($actualites as $actu): ?>
                <article class="actualite-article" data-type="<?= htmlspecialchars($actu['type'] ?? 'annonce') ?>">
                    <?php if (!empty($actu['image_url'])): ?>
                        <div class="actualite-image">
                            <img src="<?= htmlspecialchars($actu['image_url']) ?>" 
                                 alt="<?= htmlspecialchars($actu['titre']) ?>">
                        </div>
                    <?php endif; ?>
                    
                    <div class="actualite-content">
                        <div class="actualite-date">
                            <i class="far fa-calendar-alt"></i>
                            <?= date('d/m/Y', strtotime($actu['date_publication'])) ?>
                            <?php if (!empty($actu['type'])): ?>
                                <span class="actualite-type"><?= htmlspecialchars($actu['type']) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <h2 class="actualite-titre"><?= htmlspecialchars($actu['titre']) ?></h2>
                        
                        <div class="actualite-description">
                            <?= nl2br(htmlspecialchars($actu['contenu'])) ?>
                        </div>
                        
                        <a href="details_actualite.php?id=<?= $actu['id'] ?>" class="read-more">
                            Lire la suite <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Script pour les filtres
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const actualites = document.querySelectorAll('.actualite-article');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Retirer la classe active de tous les boutons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            // Ajouter la classe active au bouton cliqué
            this.classList.add('active');
            
            const filter = this.getAttribute('data-filter');
            
            // Filtrer les articles
            actualites.forEach(article => {
                const type = article.getAttribute('data-type');
                
                if (filter === 'all' || filter === type) {
                    article.style.display = 'block';
                } else {
                    article.style.display = 'none';
                }
            });
        });
    });
});
</script>

<?php require_once 'footer.php'; ?>
</body>
</html>