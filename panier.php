<?php
// panier.php - Panier d'achat uniquement
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=panier.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupérer le panier de l'utilisateur
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            l.titre,
            l.prix,
            l.couverture_url,
            a.nom_complet AS auteur,
            COUNT(DISTINCT ex.id) AS exemplaires_disponibles,
            SUM(CASE WHEN ex.statut = 'disponible' THEN 1 ELSE 0 END) AS dispo_count
        FROM panier_items p
        JOIN livres l ON p.livre_id = l.id
        JOIN auteurs a ON l.auteur_id = a.id
        LEFT JOIN exemplaires ex ON l.id = ex.livre_id
        WHERE p.user_id = ?
        GROUP BY p.id, l.id
        ORDER BY p.date_ajout DESC
    ");
    
    $stmt->execute([$user_id]);
    $panier_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer le total
    $total = 0;
    foreach ($panier_items as $item) {
        $prix = $item['type'] === 'achat' ? 
                $item['prix'] :  // Utilise directement le prix du livre
                ($item['prix'] * 0.1); // 10% du prix pour la location
        $total += $prix * $item['quantite'];
    }
    
    // Calcul des totaux
    $frais_livraison = 20.00;
    $tva = $total * 0.2;
    $total_general = $total + $frais_livraison + $tva;
    
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Panier - Bibliotech</title>
    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --primary-dark: #152a45;
            --accent: #e8b86d;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --light: #ffffff;
            
            --shadow-sm: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            
            --radius: 10px;
            --radius-full: 9999px;
            
            --transition: 250ms ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--gray-800);
            line-height: 1.6;
            background: var(--gray-100);
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header panier */
        .panier-header {
            background: var(--light);
            padding: 30px;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
        }

        .panier-header h1 {
            color: var(--primary);
            font-size: 2.5rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .panier-header h1 i {
            color: var(--accent);
            font-size: 2rem;
        }

        .panier-header p {
            color: var(--gray-600);
            font-size: 1.1rem;
        }

        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: var(--radius);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: var(--gray-800);
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: var(--gray-800);
            border-left: 4px solid var(--danger);
        }

        /* Panier vide */
        .panier-vide {
            background: var(--light);
            padding: 60px 40px;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            text-align: center;
        }

        .panier-vide i {
            font-size: 4rem;
            color: var(--gray-400);
            margin-bottom: 20px;
            display: block;
        }

        .panier-vide h2 {
            color: var(--gray-700);
            margin-bottom: 15px;
            font-size: 1.8rem;
        }

        .panier-vide p {
            color: var(--gray-600);
            margin-bottom: 30px;
            font-size: 1.1rem;
        }

        /* Boutons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: var(--radius);
            font-weight: 600;
            text-decoration: none;
            transition: all var(--transition);
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--light);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
            border: 2px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
            border-color: var(--gray-400);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
            color: var(--light);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        }

        /* Contenu panier */
        .panier-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        /* Articles panier */
        .articles-panier {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .article-panier {
            background: var(--light);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            padding: 20px;
            display: flex;
            gap: 20px;
            transition: transform var(--transition);
        }

        .article-panier:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .article-image {
            flex-shrink: 0;
            width: 120px;
            height: 180px;
            border-radius: 8px;
            overflow: hidden;
            background: var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-400);
            font-size: 2.5rem;
        }

        .article-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .article-details {
            flex: 1;
        }

        .article-title {
            color: var(--primary);
            font-size: 1.3rem;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .article-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .article-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .article-meta i {
            color: var(--accent);
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-achat {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: var(--primary);
        }

        .badge-location {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
        }

        .article-actions {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-top: 20px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--gray-100);
            border-radius: var(--radius-full);
            padding: 5px;
        }

        .btn-quantity {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: var(--light);
            color: var(--gray-700);
            font-weight: bold;
            cursor: pointer;
            transition: all var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-quantity:hover {
            background: var(--primary);
            color: var(--light);
        }

        .quantity-value {
            min-width: 40px;
            text-align: center;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .article-prix {
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 120px;
        }

        .prix-unitaire {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .prix-total {
            color: var(--primary);
            font-size: 1.3rem;
            font-weight: 700;
        }

        /* Récapitulatif */
        .recap-panier {
            background: var(--light);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            padding: 25px;
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .recap-panier h2 {
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gray-200);
        }

        .recap-ligne {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-700);
        }

        .recap-ligne:last-of-type {
            border-bottom: none;
        }

        .recap-total {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            margin: 20px 0;
        }

        .recap-actions {
            margin-top: 25px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        /* Disponibilité */
        .dispo-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .dispo-disponible {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
        }

        .dispo-attente {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .panier-content {
                grid-template-columns: 1fr;
            }
            
            .recap-panier {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .article-panier {
                flex-direction: column;
            }
            
            .article-image {
                width: 100%;
                height: 200px;
            }
            
            .article-actions {
                flex-wrap: wrap;
            }
            
            .article-prix {
                text-align: left;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }

        @media (max-width: 576px) {
            body {
                padding: 10px;
            }
            
            .panier-header h1 {
                font-size: 2rem;
            }
            
            .btn {
                padding: 10px 16px;
                font-size: 0.9rem;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <!-- Header panier -->
        <div class="panier-header">
            <h1>
                <i class="fas fa-shopping-cart"></i>
                Mon Panier
            </h1>
            <p>Gérez vos achats et locations de livres</p>
        </div>

        <!-- Messages d'alerte -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($panier_items)): ?>
            <!-- Panier vide -->
            <div class="panier-vide">
                <i class="fas fa-shopping-cart"></i>
                <h2>Votre panier est vide</h2>
                <p>Ajoutez des livres pour commencer vos achats ou locations</p>
                <a href="catalogue.php" class="btn btn-primary">
                    <i class="fas fa-book"></i> Parcourir le catalogue
                </a>
            </div>
        <?php else: ?>
            <!-- Contenu du panier -->
            <div class="panier-content">
                <!-- Section des articles -->
                <div class="articles-section">
                    <h2 style="color: var(--primary); margin-bottom: 20px; font-size: 1.5rem;">
                        Articles dans votre panier (<?= count($panier_items) ?>)
                    </h2>
                    
                    <div class="articles-panier">
                        <?php foreach ($panier_items as $item): ?>
                            <?php 
                            $prix = $item['type'] === 'achat' ? $item['prix'] : ($item['prix'] * 0.1);
                            $total_article = $prix * $item['quantite'];
                            ?>
                            
                            <div class="article-panier" id="article-<?= $item['id'] ?>">
                                <!-- Image du livre -->
                                <div class="article-image">
                                    <?php if (!empty($item['couverture_url'])): ?>
                                        <img src="<?= htmlspecialchars($item['couverture_url']) ?>" 
                                             alt="<?= htmlspecialchars($item['titre']) ?>">
                                    <?php else: ?>
                                        <i class="fas fa-book"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Détails du livre -->
                                <div class="article-details">
                                    <h3 class="article-title">
                                        <?= htmlspecialchars($item['titre']) ?>
                                    </h3>
                                    
                                    <div class="article-meta">
                                        <span>
                                            <i class="fas fa-user"></i>
                                            <?= htmlspecialchars($item['auteur']) ?>
                                        </span>
                                        <span class="badge <?= $item['type'] === 'achat' ? 'badge-achat' : 'badge-location' ?>">
                                            <?= $item['type'] === 'achat' ? '📚 Achat' : '⏳ Location (14 jours)' ?>
                                        </span>
                                        <?php if ($item['type'] === 'achat'): ?>
                                            <span class="dispo-badge <?= $item['dispo_count'] > 0 ? 'dispo-disponible' : 'dispo-attente' ?>">
                                                <i class="fas <?= $item['dispo_count'] > 0 ? 'fa-check' : 'fa-clock' ?>"></i>
                                                <?= $item['dispo_count'] > 0 ? 'Disponible' : 'En attente' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="article-actions">
                                        <div class="quantity-control">
                                            <button class="btn-quantity minus" onclick="updateQuantity(<?= $item['id'] ?>, -1)">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <span class="quantity-value"><?= $item['quantite'] ?></span>
                                            <button class="btn-quantity plus" onclick="updateQuantity(<?= $item['id'] ?>, 1)">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                        
                                        <button class="btn btn-danger" onclick="removeFromCart(<?= $item['id'] ?>)">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Prix -->
                                <div class="article-prix">
                                    <div class="prix-unitaire">
                                        <?= number_format($prix, 2) ?> DH
                                    </div>
                                    <div class="prix-total">
                                        <?= number_format($total_article, 2) ?> DH
                                    </div>
                                    <?php if ($item['quantite'] > 1): ?>
                                        <small style="color: var(--gray-500); font-size: 0.85rem;">
                                            (x<?= $item['quantite'] ?> unités)
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Bouton continuer les achats -->
                    <div style="margin-top: 30px;">
                        <a href="catalogue.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Continuer mes achats
                        </a>
                    </div>
                </div>
                
                <!-- Récapitulatif -->
                <div class="recap-panier">
                    <h2>Récapitulatif</h2>
                    
                    <div class="recap-ligne">
                        <span>Sous-total :</span>
                        <strong><?= number_format($total, 2) ?> DH</strong>
                    </div>
                    
                    <div class="recap-ligne">
                        <span>Frais de livraison :</span>
                        <strong><?= number_format($frais_livraison, 2) ?> DH</strong>
                    </div>
                    
                    <div class="recap-ligne">
                        <span>TVA (20%) :</span>
                        <strong><?= number_format($tva, 2) ?> DH</strong>
                    </div>
                    
                    <hr style="margin: 20px 0; border: none; border-top: 2px solid var(--gray-200);">
                    
                    <div class="recap-ligne recap-total">
                        <span>Total :</span>
                        <span><?= number_format($total_general, 2) ?> DH</span>
                    </div>
                    
                    <div class="recap-actions">
                        <a href="simulateur_paiement.php" class="btn btn-primary">
                            <i class="fas fa-credit-card"></i> Procéder au paiement
                        </a>
                    
                    <div style="margin-top: 20px; padding: 15px; background: var(--gray-100); border-radius: var(--radius);">
                        <p style="color: var(--gray-600); font-size: 0.9rem; margin-bottom: 5px;">
                            <i class="fas fa-shield-alt" style="color: var(--primary);"></i>
                            Paiement sécurisé SSL
                        </p>
                        <p style="color: var(--gray-500); font-size: 0.8rem;">
                            Livraison sous 2-5 jours ouvrés
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function updateQuantity(itemId, change) {
        fetch('update_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                item_id: itemId,
                change: change
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Animation de mise à jour
                const article = document.getElementById(`article-${itemId}`);
                if (article) {
                    article.style.transform = 'scale(1.02)';
                    setTimeout(() => {
                        article.style.transform = '';
                    }, 300);
                }
                location.reload();
            } else {
                alert('Erreur: ' + (data.message || 'Impossible de mettre à jour la quantité'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erreur réseau');
        });
    }

    function removeFromCart(itemId) {
        if (confirm('Supprimer cet article du panier ?')) {
            fetch('remove_from_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ item_id: itemId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Animation de suppression
                    const article = document.getElementById(`article-${itemId}`);
                    if (article) {
                        article.style.opacity = '0.5';
                        article.style.transform = 'translateX(-100px)';
                        setTimeout(() => {
                            location.reload();
                        }, 300);
                    }
                } else {
                    alert('Erreur: ' + (data.message || 'Impossible de supprimer l\'article'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur réseau');
            });
        }
    }
    </script>
</body>
</html>