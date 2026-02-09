<?php
// mes_emprunts.php - Page des emprunts en cours
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=mes_emprunts.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupérer les emprunts en cours
try {
    $stmt = $pdo->prepare("
        SELECT 
            e.*,
            l.titre,
            l.prix,
            l.couverture_url,
            a.nom_complet AS auteur,
            DATEDIFF(e.date_retour_prevue, CURDATE()) AS jours_restants
        FROM emprunts e
        JOIN exemplaires ex ON e.exemplaire_id = ex.id
        JOIN livres l ON ex.livre_id = l.id
        JOIN auteurs a ON l.auteur_id = a.id
        WHERE e.membre_id = ? 
        ORDER BY e.date_emprunt DESC
    ");
    
    $stmt->execute([$user_id]);
    $emprunts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Emprunts - Bibliotech</title>
</head>
<body>

<h1>Mes Emprunts</h1>

<?php if (empty($emprunts)): ?>
    <div>
        <p>📚 Vous n'avez aucun emprunt</p>
        <a href="catalogue.php">Découvrir des livres à emprunter</a>
    </div>
<?php else: ?>
    <h2>Mes emprunts (<?= count($emprunts) ?>)</h2>
    
    <?php 
    // Séparer les emprunts en cours et terminés
    $emprunts_en_cours = array_filter($emprunts, function($e) {
        return $e['statut'] === 'en_cours' || empty($e['date_retour_effective']);
    });
    
    $emprunts_termines = array_filter($emprunts, function($e) {
        return $e['statut'] === 'termine' || !empty($e['date_retour_effective']);
    });
    ?>
    
    <?php if (!empty($emprunts_en_cours)): ?>
        <h3>Emprunts en cours (<?= count($emprunts_en_cours) ?>)</h3>
        <?php foreach ($emprunts_en_cours as $emprunt): ?>
            <div style="border: 1px solid #4CAF50; padding: 15px; margin-bottom: 15px; background: #f0f9f0;">
                <div style="display: flex; gap: 15px;">
                    <?php if (!empty($emprunt['couverture_url'])): ?>
                        <img src="<?= htmlspecialchars($emprunt['couverture_url']) ?>" 
                             alt="<?= htmlspecialchars($emprunt['titre']) ?>" 
                             style="width: 100px; height: 150px;">
                    <?php else: ?>
                        <div style="width: 100px; height: 150px; background: #ddd; display: flex; align-items: center; justify-content: center;">
                            📚
                        </div>
                    <?php endif; ?>
                    
                    <div style="flex: 1;">
                        <h3><?= htmlspecialchars($emprunt['titre']) ?></h3>
                        <p><strong>Auteur :</strong> <?= htmlspecialchars($emprunt['auteur']) ?></p>
                        
                        <p><strong>Emprunté le :</strong> <?= date('d/m/Y', strtotime($emprunt['date_emprunt'])) ?></p>
                        <p><strong>Retour prévu le :</strong> <?= date('d/m/Y', strtotime($emprunt['date_retour_prevue'])) ?></p>
                        
                        <?php if ($emprunt['jours_restants'] < 0): ?>
                            <p style="color: red;"><strong>⚠️ En retard de <?= abs($emprunt['jours_restants']) ?> jours</strong></p>
                        <?php else: ?>
                            <p><strong>Jours restants :</strong> <?= $emprunt['jours_restants'] ?> jours</p>
                        <?php endif; ?>
                        
                        <?php if ($emprunt['prolongations'] > 0): ?>
                            <p><strong>Prolongations :</strong> <?= $emprunt['prolongations'] ?>/<?= $emprunt['prolongation_max'] ?></p>
                        <?php endif; ?>
                        
                        <?php if ($emprunt['amende_cumulee'] > 0): ?>
                            <p style="color: red;"><strong>Amende :</strong> <?= number_format($emprunt['amende_cumulee'], 2) ?> DH</p>
                        <?php endif; ?>
                        
                        <div style="margin-top: 10px;">
                            <?php if ($emprunt['prolongations'] < $emprunt['prolongation_max']): ?>
                                <button onclick="prolongerEmprunt(<?= $emprunt['id'] ?>)">⏱️ Prolonger (7 jours)</button>
                            <?php endif; ?>
                            
                            <button onclick="retournerEmprunt(<?= $emprunt['id'] ?>)">📖 Retourner ce livre</button>
                            
                            <?php if ($emprunt['amende_cumulee'] > 0): ?>
                                <button onclick="payerAmende(<?= $emprunt['id'] ?>)">💳 Payer amende</button>
                            <?php endif; ?>
                            
                            <a href="details_livre.php?id=<?= $e['livre_id'] ?>" 
                   style="display: inline-block; padding: 8px 15px; border: 1px solid #1e6a72; color: #1e6a72; text-decoration: none;">
                    👁️ Voir détails
                </a>
            </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (!empty($emprunts_termines)): ?>
        <h3>Emprunts terminés (<?= count($emprunts_termines) ?>)</h3>
        <?php foreach ($emprunts_termines as $emprunt): ?>
            <div style="border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; background: #f9f9f9;">
                <div style="display: flex; gap: 15px;">
                    <?php if (!empty($emprunt['couverture_url'])): ?>
                        <img src="<?= htmlspecialchars($emprunt['couverture_url']) ?>" 
                             alt="<?= htmlspecialchars($emprunt['titre']) ?>" 
                             style="width: 100px; height: 150px;">
                    <?php else: ?>
                        <div style="width: 100px; height: 150px; background: #ddd; display: flex; align-items: center; justify-content: center;">
                            📚
                        </div>
                    <?php endif; ?>
                    
                    <div style="flex: 1;">
                        <h3><?= htmlspecialchars($emprunt['titre']) ?></h3>
                        <p><strong>Auteur :</strong> <?= htmlspecialchars($emprunt['auteur']) ?></p>
                        
                        <p><strong>Emprunté le :</strong> <?= date('d/m/Y', strtotime($emprunt['date_emprunt'])) ?></p>
                        <p><strong>Retourné le :</strong> <?= date('d/m/Y', strtotime($emprunt['date_retour_effective'])) ?></p>
                        
                        <?php if ($emprunt['amende_cumulee'] > 0): ?>
                            <p><strong>Amende payée :</strong> <?= number_format($emprunt['amende_cumulee'], 2) ?> DH</p>
                        <?php else: ?>
                            <p>✅ Emprunt terminé sans amende</p>
                        <?php endif; ?>
                        
                        <?php if (!empty($emprunt['notes'])): ?>
                            <p><strong>Note :</strong> <?= htmlspecialchars($emprunt['notes']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<div style="margin-top: 20px;">
    <a href="catalogue.php">Emprunter d'autres livres</a> | 
    <a href="panier.php">Voir mon panier</a>
</div>

<script>
function prolongerEmprunt(empruntId) {
    if (confirm('Prolonger cet emprunt de 7 jours supplémentaires ?')) {
        window.location.href = 'prolonger_emprunt.php?id=' + empruntId;
    }
}

function retournerEmprunt(empruntId) {
    if (confirm('Marquer ce livre comme retourné ?')) {
        window.location.href = 'retourner_emprunt.php?id=' + empruntId;
    }
}

function payerAmende(empruntId) {
    if (confirm('Payer l\'amende pour cet emprunt ?')) {
        window.location.href = 'payer_amende.php?id=' + empruntId;
    }
}
</script>

</body>
</html>