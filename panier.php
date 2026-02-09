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
</head>
<body>

<h1>Mon Panier</h1>
<?php
if (isset($_SESSION['success_message'])) {
    echo '<div style="background:#d4edda; color:#155724; padding:15px; border-radius:8px; margin-bottom:20px;">'
         . htmlspecialchars($_SESSION['success_message']) .
         '</div>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<div style="background:#f8d7da; color:#721c24; padding:15px; border-radius:8px; margin-bottom:20px;">'
         . htmlspecialchars($_SESSION['error_message']) .
         '</div>';
    unset($_SESSION['error_message']);
}
?>
<p>Gérez vos achats et locations</p>

<?php if (empty($panier_items)): ?>
    <div>
        <p>🛒 Votre panier est vide</p>
        <p>Ajoutez des livres pour commencer vos achats</p>
        <a href="catalogue.php">Parcourir le catalogue</a>
    </div>
<?php else: ?>
    <h2>Articles dans votre panier (<?= count($panier_items) ?>)</h2>
    
    <?php foreach ($panier_items as $item): ?>
        <div style="border: 1px solid #ddd; padding: 15px; margin-bottom: 15px;">
            <div style="display: flex; gap: 15px;">
                <?php if (!empty($item['couverture_url'])): ?>
                    <img src="<?= htmlspecialchars($item['couverture_url']) ?>" 
                         alt="<?= htmlspecialchars($item['titre']) ?>" 
                         style="width: 100px; height: 150px;">
                <?php else: ?>
                    <div style="width: 100px; height: 150px; background: #ddd; display: flex; align-items: center; justify-content: center;">
                        📚
                    </div>
                <?php endif; ?>
                
                <div style="flex: 1;">
                    <h3><?= htmlspecialchars($item['titre']) ?></h3>
                    <p><strong>Auteur :</strong> <?= htmlspecialchars($item['auteur']) ?></p>
                    <p><strong>Type :</strong> <?= $item['type'] === 'achat' ? 'Achat' : 'Location' ?></p>
                    
                    <?php if ($item['type'] === 'achat'): ?>
                        <p><strong>Disponibilité :</strong> <?= $item['dispo_count'] > 0 ? '✅ Disponible' : '⏳ En réapprovisionnement' ?></p>
                    <?php else: ?>
                        <p><strong>Durée :</strong> 14 jours</p>
                    <?php endif; ?>
                    
                    <div style="display: flex; gap: 15px; align-items: center; margin-top: 10px;">
                        <div>
                            <strong>Quantité :</strong>
                            <button onclick="updateQuantity(<?= $item['id'] ?>, -1)">-</button>
                            <span><?= $item['quantite'] ?></span>
                            <button onclick="updateQuantity(<?= $item['id'] ?>, 1)">+</button>
                        </div>
                        
                        <div>
                            <strong>Prix :</strong>
                            <?php 
                            $prix = $item['type'] === 'achat' ? 
                                    $item['prix'] : // Prix normal du livre
                                    ($item['prix'] * 0.1); // 10% pour la location
                            echo number_format($prix, 2) . ' DH';
                            ?>
                            <?php if ($item['quantite'] > 1): ?>
                                <small>(x<?= $item['quantite'] ?> = <?= number_format($prix * $item['quantite'], 2) ?> DH)</small>
                            <?php endif; ?>
                        </div>
                        
                        <button onclick="removeFromCart(<?= $item['id'] ?>)">🗑️ Supprimer</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <!-- Récapitulatif -->
    <div style="border: 1px solid #ddd; padding: 20px; margin-top: 20px;">
        <h3>Récapitulatif de la commande</h3>
        
        <p><strong>Sous-total :</strong> <?= number_format($total, 2) ?> DH</p>
        <p><strong>Frais de livraison :</strong> 20.00 DH</p>
        <p><strong>TVA (20%) :</strong> <?= number_format($total * 0.2, 2) ?> DH</p>
        <hr>
        <h3>Total : <?= number_format($total + 20 + ($total * 0.2), 2) ?> DH</h3>
        
        <a href="simulateur_paiement.php" style="display: inline-block; padding: 10px 20px; background: #1e6a72; color: white; text-decoration: none;">
            Tester le paiement (simulation)
        </a>
    </div>
    
    <div style="margin-top: 20px;">
        <a href="catalogue.php">Continuer mes achats</a>
    </div>
<?php endif; ?>

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
                location.reload();
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