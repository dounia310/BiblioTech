<?php
session_start();
unset($_SESSION['pending_commande']);

echo "<h1>Paiement SIMULÉ annulé / échoué 😔</h1>";
echo "<p>Réessaie depuis ton panier.</p>";
echo "<a href='panier.php'>Retour au panier</a>";
?>