<?php
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT c.*, l.titre, a.nom_complet AS auteur 
        FROM commandes c 
        JOIN livres l ON c.livre_id = l.id 
        LEFT JOIN auteurs a ON l.auteur_id = a.id
        WHERE c.user_id = ? AND c.type IN ('achat_physique', 'achat_numerique') 
        ORDER BY c.date_commande DESC
    ");
    $stmt->execute([$user_id]);
    $achats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur mes_achats : " . $e->getMessage());
    $achats = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Achats - Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
:root{--primary:#2b848e;--success:#7ab781;--danger:#dc3545;--dark:#254c5b;--light:#f8f9fa;--gray:#e9ecef;--radius:12px;}*{box-sizing:border-box;margin:0;padding:0;}body{font-family:system-ui,sans-serif;background:var(--light);color:var(--dark);line-height:1.6;}.container{max-width:900px;margin:0 auto;padding:2rem 1rem;}h1{font-size:2.2rem;color:var(--primary);text-align:center;margin-bottom:2rem;text-shadow:0 2px 4px rgba(0,0,0,0.1);}.no-achats{text-align:center;padding:4rem 2rem;background:white;border-radius:var(--radius);box-shadow:0 10px 30px rgba(0,0,0,0.1);}.no-achats i{font-size:5rem;color:var(--gray);margin-bottom:1rem;}.achat-card{background:white;border-radius:var(--radius);padding:1.5rem;margin-bottom:1.5rem;box-shadow:0 6px 20px rgba(0,0,0,0.08);border-left:5px solid var(--primary);transition:all 0.3s ease;}.achat-card:hover{transform:translateY(-2px);box-shadow:0 12px 40px rgba(0,0,0,0.15);}.achat-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:1rem;}.achat-header h3{margin:0;font-size:1.4rem;color:var(--dark);}.statut{padding:.6rem 1.2rem;border-radius:20px;font-size:.9rem;font-weight:600;text-transform:uppercase;}.statut-paye{background:var(--success);color:#155724;}.statut-en_attente{background:var(--warning);color:#856404;}.statut-echec{background:var(--danger);color:white;}.info-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin:1rem 0;font-size:.95rem;}.info-row strong{color:var(--primary);}.btn-telecharger{display:inline-block;background:var(--primary);color:white;padding:.8rem 1.6rem;border-radius:8px;text-decoration:none;font-weight:600;transition:all .2s;margin-top:.5rem;}.btn-telecharger:hover{background:var(--dark);transform:translateY(-1px);box-shadow:0 4px 12px rgba(43,132,142,0.3);}.btn-vider{background:var(--danger);color:white;padding:.8rem 1.6rem;border:none;border-radius:8px;cursor:pointer;font-weight:600;display:block;margin:2rem auto 1rem;width:200px;transition:all .2s;}.btn-vider:hover{background:#c82333;transform:translateY(-1px);}@media(max-width:768px){.container{padding:1rem;}.achat-header{flex-direction:column;text-align:center;}}</style>
</head>
<body>
<div class="container">
    <h1><i class="fas fa-shopping-bag"></i> Mes Achats</h1>
    
    <?php if (empty($achats)): ?>
        <div class="no-achats">
            <i class="fas fa-shopping-bag"></i>
            <h2>Aucun achat effectué</h2>
            <p>Explorez notre catalogue pour ajouter des livres à votre collection !</p>
            <a href="catalogue.php" style="color:var(--primary);font-weight:600;font-size:1.1rem;">→ Parcourir les livres</a>
        </div>
    <?php else: ?>
        <div style="text-align:center;margin-bottom:1.5rem;font-size:1.1rem;color:var(--gray);">
            <?= count($achats) ?> achat<?= count($achats)>1 ? 's' : '' ?> au total
        </div>
        
        <?php foreach ($achats as $achat): ?>
            <div class="achat-card">
                <div class="achat-header">
                    <h3><?= htmlspecialchars($achat['titre'] ?? 'Livre') ?> <small>par <?= htmlspecialchars($achat['auteur'] ?? '—') ?></small></h3>
                    <span class="statut statut-<?= strtolower($achat['statut_paiement'] ?? 'en_attente') ?>">
                        <?= ucfirst(htmlspecialchars($achat['statut_paiement'] ?? 'En attente')) ?>
                    </span>
                </div>
                
                <div class="info-row">
                    <div><strong>Commande :</strong> #<?= htmlspecialchars($achat['numero_commande'] ?? $achat['id']) ?></div>
                    <div><strong>Date :</strong> <?= $achat['date_commande'] ? date('d/m/Y H:i', strtotime($achat['date_commande'])) : '—' ?></div>
                    <div><strong>Montant :</strong> <?= number_format($achat['montant_total'] ?? 0, 2) ?> DH</div>
                    <div><strong>Type :</strong> <?= $achat['type'] === 'achat_numerique' ? '<i class="fas fa-download" style="color:var(--success);"></i> Numérique' : '<i class="fas fa-box" style="color:var(--primary);"></i> Physique' ?></div>
                </div>
                
                <?php if ($achat['type'] === 'achat_numerique' && $achat['statut_paiement'] === 'paye'): ?>
                    <a href="telecharger.php?id=<?= $achat['id'] ?>" class="btn-telecharger">
                        <i class="fas fa-download"></i> Télécharger l'ebook maintenant
                    </a>
                <?php elseif ($achat['type'] === 'achat_physique'): ?>
                    <p style="margin-top:1rem;color:var(--gray);"><i class="fas fa-truck"></i> Livraison : <?= htmlspecialchars($achat['adresse_livraison'] ?? $achat['statut'] ?? 'En préparation') ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <button onclick="location.href='process_commande.php?action=vider_panier'" class="btn-vider">
            <i class="fas fa-trash"></i> Vider l'historique des achats
        </button>
    <?php endif; ?>
    
    <div style="text-align:center;margin-top:3rem;">
        <a href="panier.php" style="color:var(--gray);margin-right:2rem;"><i class="fas fa-shopping-cart"></i> Mon panier</a>
        <a href="catalogue.php" style="color:var(--primary);font-weight:600;"><i class="fas fa-book"></i> Continuer les achats</a>
    </div>
</div>
</body>
</html>