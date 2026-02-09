<?php
session_start();

// Simule la création de la commande (adapte à ta BD)
$_SESSION['pending_commande'] = [
    'montant' => 150, // exemple
    'type' => 'achat_numerique',
    'livre_id' => 42, // exemple
];

// Redirige vers le simulateur local
header('Location: simulateur_paiement.php');
exit;
?>