<?php
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paiement - Simulation</title>
    <style>
        body { font-family: Arial; text-align: center; padding: 80px; }
        .btn { padding: 15px 35px; font-size: 18px; margin: 20px; cursor: pointer; }
        .success { background: #28a745; color: white; border: none; }
        .danger  { background: #dc3545; color: white; border: none; }
    </style>
</head>
<body>

<h1>Simulation de paiement</h1>
<p style="font-size: 1.3rem;">Montant total : 185.00 DH (exemple)</p>
<p>Ceci est un test – pas d'argent réel</p>

<a href="paiement_success.php"><button class="btn success">Payer (réussi)</button></a>
<a href="paiement_cancel.php"><button class="btn danger">Annuler</button></a>

</body>
</html>