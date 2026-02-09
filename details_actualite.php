<?php
// details_actualite.php - Version corrigée sans dépendre de colonnes manquantes
require_once 'header.php';

// Vérifier si l'ID est présent
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: actualites.php');
    exit();
}

$actualite_id = (int)$_GET['id'];

// REQUÊTE SIMPLIFIÉE - Sans jointure ni colonnes manquantes
try {
    $stmt = $pdo->prepare("
        SELECT * 
        FROM nouvelles 
        WHERE id = ? AND statut = 'publie'
    ");
    $stmt->execute([$actualite_id]);
    $actualite = $stmt->fetch();
    
    if (!$actualite) {
        throw new Exception("Actualité non trouvée");
    }
    
} catch (Exception $e) {
    echo '<div class="container" style="padding: 50px 20px;">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> 
                ' . htmlspecialchars($e->getMessage()) . '
            </div>
            <a href="actualites.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Retour aux actualités
            </a>
          </div>';
    require_once 'footer.php';
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($actualite['titre']) ?> - Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
     body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background-color: #f5f7fa; margin: 0; padding: 0; } .container { max-width: 900px; margin: 0 auto; padding: 20px; } .btn { display: inline-block; padding: 10px 20px; background-color: var(--primary); color: white; text-decoration: none; border-radius: var(--radius); border: none; cursor: pointer; font-size: 16px; transition: background-color 0.3s; } .btn:hover { background-color: #3a5a8a; } .btn-outline-primary { background-color: transparent; border: 2px solid var(--primary); color: var(--primary); } .btn-outline-primary:hover { background-color: var(--primary); color: white; } .alert { padding: 15px; border-radius: var(--radius); margin-bottom: 20px; } .alert-danger { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; } .alert-success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; } .alert-info { background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; } .badge { display: inline-block; padding: 4px 8px; font-size: 12px; font-weight: bold; border-radius: 12px; margin-left: 8px; } .bg-primary { background-color: var(--primary); color: white; } .bg-danger { background-color: #dc3545; color: white; } .actualite-header { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 40px; border-radius: var(--radius); margin-bottom: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); } .actualite-type-badge { background-color: var(--primary); color: white; padding: 6px 16px; border-radius: 20px; display: inline-block; margin-bottom: 15px; font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; } .actualite-title { font-size: 2.5rem; color: var(--dark); margin-bottom: 20px; line-height: 1.3; font-weight: 700; } .actualite-meta { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; color: var(--gray-600); padding-top: 15px; border-top: 1px solid #dee2e6; } .actualite-date { display: flex; align-items: center; gap: 8px; font-size: 0.95rem; } .actualite-image { margin: 30px 0; text-align: center; } .actualite-image img { max-width: 100%; max-height: 500px; object-fit: contain; border-radius: var(--radius); box-shadow: 0 5px 15px rgba(0,0,0,0.1); } .actualite-content { font-size: 1.1rem; line-height: 1.8; color: var(--gray-800); padding: 20px; background: white; border-radius: var(--radius); box-shadow: 0 2px 4px rgba(0,0,0,0.05); } .actualite-content h2 { color: var(--dark); margin: 30px 0 15px; font-size: 1.8rem; font-weight: 600; } .actualite-content h3 { color: var(--dark); margin: 25px 0 12px; font-size: 1.5rem; font-weight: 600; } .actualite-content p { margin-bottom: 20px; } .actualite-content ul, .actualite-content ol { margin-left: 25px; margin-bottom: 20px; } .actualite-content li { margin-bottom: 10px; } .actualite-content blockquote { border-left: 4px solid var(--primary); padding-left: 20px; margin: 25px 0; font-style: italic; color: var(--gray-600); } .commentaires-section { margin-top: 50px; padding-top: 30px; border-top: 2px solid #eee; } .commentaires-title { font-size: 1.8rem; margin-bottom: 25px; color: var(--dark); font-weight: 600; } .form-title { font-size: 1.4rem; margin-bottom: 20px; color: var(--dark); font-weight: 600; } .form-group { margin-bottom: 20px; } .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: var(--radius); font-size: 16px; font-family: inherit; } textarea.form-control { min-height: 120px; resize: vertical; } .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(74, 111, 165, 0.1); } @media (max-width: 768px) { .container { padding: 15px; } .actualite-header { padding: 25px; } .actualite-title { font-size: 2rem; } .actualite-meta { flex-direction: column; align-items: flex-start; gap: 15px; } .actualite-image img { max-height: 300px; } } @media (max-width: 480px) { .actualite-title { font-size: 1.8rem; } .actualite-header { padding: 20px; } .actualite-content { padding: 15px; font-size: 1rem; } } .fas, .far { margin-right: 8px; }
    </style>

   </head>
<body>

<div class="container">
    <!-- En-tête de l'actualité -->
    <div class="actualite-header">
        <?php if (!empty($actualite['type'])): ?>
            <div class="actualite-type-badge">
                <?= htmlspecialchars($actualite['type']) ?>
            </div>
        <?php endif; ?>
        
        <h1 class="actualite-title"><?= htmlspecialchars($actualite['titre']) ?></h1>
        
        <div class="actualite-meta">
            <div class="actualite-date">
                <i class="far fa-calendar-alt"></i>
                Publié le <?= date('d/m/Y à H:i', strtotime($actualite['date_publication'])) ?>
            </div>
            
            <div>
                <a href="actualites.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Retour aux actualités
                </a>
            </div>
        </div>
    </div>
    
    <!-- Image principale -->
    <?php if (!empty($actualite['image_url'])): ?>
        <div class="actualite-image">
            <img src="<?= htmlspecialchars($actualite['image_url']) ?>" 
                 alt="<?= htmlspecialchars($actualite['titre']) ?>"
                 onerror="this.src='https://via.placeholder.com/800x400/4a6fa5/ffffff?text=Bibliotech'">
        </div>
    <?php endif; ?>
    
    <!-- Contenu -->
    <div class="actualite-content">
        <?= nl2br(htmlspecialchars($actualite['contenu'])) ?>
    </div>
    
    <!-- Boutons d'action -->
    <div style="margin-top: 40px; display: flex; gap: 15px; justify-content: center;">
        <a href="actualites.php" class="btn">
            <i class="fas fa-newspaper"></i> Voir toutes les actualités
        </a>
        
        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'): ?>
            <a href="modifier_actualite.php?id=<?= $actualite_id ?>" class="btn" style="background-color: #28a745;">
                <i class="fas fa-edit"></i> Modifier
            </a>
        <?php endif; ?>
    </div>
</div>

<script>
// Script pour améliorer l'expérience utilisateur
document.addEventListener('DOMContentLoaded', function() {
    // Vérifier les images cassées
    document.querySelectorAll('img').forEach(img => {
        img.onerror = function() {
            this.src = 'https://via.placeholder.com/800x400/4a6fa5/ffffff?text=Image+Non+Disponible';
            this.alt = 'Image non disponible';
        };
    });
    
    // Ajouter une animation de chargement
    const images = document.querySelectorAll('img');
    images.forEach(img => {
        if (!img.complete) {
            img.style.opacity = '0.5';
            img.style.transition = 'opacity 0.3s';
            img.onload = function() {
                this.style.opacity = '1';
            };
        }
    });
    
    // Confirmation avant de quitter si un formulaire est rempli
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        let formChanged = false;
        
        form.querySelectorAll('input, textarea, select').forEach(input => {
            input.addEventListener('input', () => {
                formChanged = true;
            });
        });
        
        window.addEventListener('beforeunload', (e) => {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    });
});
</script>

<?php 
// Afficher les messages de session
if (isset($_SESSION['error_message'])) {
    echo '<div class="container"><div class="alert alert-danger">' . $_SESSION['error_message'] . '</div></div>';
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['success_message'])) {
    echo '<div class="container"><div class="alert alert-success">' . $_SESSION['success_message'] . '</div></div>';
    unset($_SESSION['success_message']);
}

require_once 'footer.php'; 
?>
</body>
</html>