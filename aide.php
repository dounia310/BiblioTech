<?php
require_once 'connexion.php';
require_once 'header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aide – Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8fafc;
            color: #0f172a;
            line-height: 1.7;
            margin: 0;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.4rem;
            text-align: center;
            margin-bottom: 2rem;
            color: #1e3a5f;
        }

        .help-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .help-section h2 {
            font-size: 1.6rem;
            color: #1e3a5f;
            margin: 0 0 1.2rem;
            border-bottom: 2px solid #e8b86d;
            padding-bottom: 0.6rem;
        }

        .help-section ul {
            list-style: none;
            padding-left: 0;
        }

        .help-section li {
            margin-bottom: 0.8rem;
            padding-left: 1.8rem;
            position: relative;
        }

        .help-section li::before {
            content: '\f058';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: #10b981;
            position: absolute;
            left: 0;
        }

        .contact-box {
            text-align: center;
            padding: 2rem;
            background: #f1f5f9;
            border-radius: 12px;
            margin-top: 2rem;
        }

        .contact-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #1e3a5f;
            color: white;
            padding: 0.9rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s;
        }

        .contact-btn:hover {
            background: #2d5a87;
        }

        @media (max-width: 768px) {
            h1 { font-size: 2rem; }
            .help-section { padding: 1.5rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Centre d’aide Bibliotech</h1>

    <div class="help-section">
        <h2>Comment emprunter un livre ?</h2>
        <ol>
            <li>Connectez-vous à votre compte (ou créez-en un).</li>
            <li>Recherchez le livre dans le catalogue.</li>
            <li>Cliquez sur « Détails » puis sur « Emprunter » (si disponible).</li>
            <li>Choisissez la durée : 7, 14 ou 21 jours maximum.</li>
            <li>Confirmez → vous recevez un e-mail avec la date limite de retour.</li>
        </ol>
        <p><strong>Le livre est déjà emprunté ?</strong> Cliquez sur « Réserver » pour être averti dès qu’il est disponible.</p>
    </div>

    <div class="help-section">
        <h2>Comment acheter un livre ?</h2>
        <ol>
            <li>Ajoutez le livre au panier via « Ajouter au panier ».</li>
            <li>Allez dans votre panier et cliquez sur « Passer commande ».</li>
            <li>Choisissez le mode de paiement (carte ou virement).</li>
            <li>Validez → livraison en 24–72h au Maroc (gratuite dès 300 DH).</li>
        </ol>
    </div>

    <div class="help-section">
        <h2>Retour et remboursement</h2>
        <ul>
            <li>Retour possible sous 14 jours pour les achats (livre intact).</li>
            <li>Étiquette de retour prépayée fournie pour les envois.</li>
            <li>Remboursement traité sous 7 jours après réception.</li>
        </ul>
    </div>

    <div class="help-section">
        <h2>Sécurité et confidentialité</h2>
        <ul>
            <li>Paiements sécurisés via CMI (SSL).</li>
            <li>Vos données ne sont jamais vendues.</li>
            <li>Consultez notre <a href="confidentialite.php">politique de confidentialité</a>.</li>
        </ul>
    </div>

    <div class="help-section">
        <h2>Délais et pénalités</h2>
        <ul>
            <li>Retard d’emprunt : 5 DH par jour après la date limite.</li>
            <li>Perte ou détérioration : prix du livre + 50 DH de frais.</li>
        </ul>
    </div>

    <div class="contact-box">
        <h3>Besoin d’aide supplémentaire ?</h3>
        <p>Écrivez-nous à : <strong>support@bibliotech.ma</strong><br>
           WhatsApp : +212 6 XX XX XX XX<br>
           (Lundi–Samedi, 9h–19h)</p>
        <a href="contact.php" class="contact-btn">
            <i class="fas fa-envelope"></i> Nous contacter
        </a>
    </div>
</div>

<?php require_once 'footer.php'; ?>
</body>
</html>