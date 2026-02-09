<?php
session_start();
require 'config.php'; // ton PDO ou connexion BD

if (!isset($_SESSION['pending_commande'])) die('Erreur');

$commande = $_SESSION['pending_commande'];

// Mets à jour ta table commandes (exemple simplifié)
$pdo = new PDO('mysql:host=localhost;dbname=bibliotech', 'root', '');
$pdo->prepare("UPDATE commandes SET 
    statut_paiement = 'paye', 
    date_paiement = NOW(), 
    transaction_id = 'SIM-TEST-" . time() . "',
    statut = 'paye' 
    WHERE user_id = ? AND type = ?") // adapte selon ta clé
    ->execute([$_SESSION['user_id'], $commande['type']]);

unset($_SESSION['pending_commande']);

echo "<h1>Paiement SIMULÉ réussi ! 🎉</h1>";
echo "<p>Ton achat est maintenant confirmé dans la base de données.</p>";
echo "<a href='mes_achats.php'>Voir mes achats</a>";
?>