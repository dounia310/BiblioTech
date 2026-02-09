<?php session_start(); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Simulateur Paiement Bibliotech (TEST LOCAL)</title>
    <style> body { font-family: Arial; text-align: center; padding: 50px; } button { padding: 15px 30px; margin: 20px; font-size: 18px; } </style>
</head>
<body>
    <h1>Simulateur de Paiement CMI (LOCAL - PAS D'ARGENT RÉEL)</h1>
    <p>Montant : 150 MAD (exemple)</p>
    <p>Ceci est un test pour ton projet. Choisis le résultat :</p>
    
    <a href="success.php?sim=success"><button style="background: green; color: white;">Paiement RÉUSSI</button></a>
    <a href="cancel.php?sim=echec"><button style="background: red; color: white;">Paiement ÉCHOUÉ</button></a>
</body>
</html>