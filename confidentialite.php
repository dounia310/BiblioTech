<?php
require_once 'connexion.php'; // si besoin pour header/footer
require_once 'header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Politique de confidentialité – Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --primary-dark: #152a45;
            --accent: #e8b86d;
            --accent-hover: #d4a55c;
            --dark: #0f172a;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --radius-md: 16px;
            --transition: 0.3s ease;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--gray-50);
            color: var(--dark);
            line-height: 1.7;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .page-hero {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            color: white;
            padding: 5rem 2rem 3rem;
            text-align: center;
            margin-bottom: 3rem;
            border-radius: 0 0 24px 24px;
            box-shadow: var(--shadow-lg);
        }

        .page-hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            margin-bottom: 0.8rem;
        }

        .page-hero p {
            font-size: 1.2rem;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.95;
        }

        .privacy-content {
            background: white;
            padding: 3rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            margin-bottom: 4rem;
        }

        .privacy-content h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            color: var(--primary);
            margin: 2.5rem 0 1.2rem;
            border-bottom: 2px solid var(--accent);
            padding-bottom: 0.6rem;
        }

        .privacy-content h3 {
            font-size: 1.5rem;
            margin: 2rem 0 1rem;
            color: var(--primary-dark);
        }

        .privacy-content p {
            margin-bottom: 1.2rem;
            color: var(--gray-700);
        }

        .privacy-content ul {
            list-style: none;
            padding-left: 0;
            margin-bottom: 1.5rem;
        }

        .privacy-content ul li {
            padding: 0.8rem 0;
            position: relative;
            padding-left: 2rem;
        }

        .privacy-content ul li::before {
            content: '\f058';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: var(--success);
            position: absolute;
            left: 0;
            top: 0.9rem;
        }

        .privacy-content .highlight {
            background: var(--gray-50);
            padding: 1.2rem;
            border-radius: 12px;
            border-left: 5px solid var(--accent);
            margin: 1.5rem 0;
        }

        .last-update {
            text-align: center;
            color: var(--gray-500);
            font-style: italic;
            margin: 3rem 0;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            margin: 2rem 0;
            transition: all var(--transition);
        }

        .back-link:hover {
            color: var(--primary-dark);
            gap: 15px;
        }

        @media (max-width: 768px) {
            .page-hero { padding: 4rem 1.5rem 2.5rem; }
            .page-hero h1 { font-size: 2.4rem; }
            .privacy-content { padding: 2rem; }
        }
    </style>
</head>
<body>

    <section class="page-hero">
        <div class="container">
            <h1>Politique de confidentialité</h1>
            <p>Nous protégeons vos données avec le plus grand soin. Voici comment nous collectons, utilisons et protégeons vos informations.</p>
        </div>
    </section>

    <div class="container">
        <div class="privacy-content">
            <h2>1. Données que nous collectons</h2>
            <p>Nous collectons uniquement les informations strictement nécessaires pour vous offrir le meilleur service :</p>
            <ul>
                <li>Vos informations de compte : nom, prénom, e-mail, numéro de téléphone (facultatif)</li>
                <li>Historique de vos emprunts, réservations et achats</li>
                <li>Informations de paiement (gérées de manière sécurisée par notre prestataire CMI – nous n’avons jamais accès aux détails de votre carte)</li>
                <li>Messages envoyés via le formulaire de contact ou support</li>
                <li>Données techniques : adresse IP, type de navigateur, pages visitées (pour améliorer le site)</li>
            </ul>

            <h2>2. Pourquoi nous collectons ces données</h2>
            <p>Vos données sont utilisées uniquement pour :</p>
            <ul>
                <li>Créer et gérer votre compte membre</li>
                <li>Traiter vos emprunts, réservations et achats</li>
                <li>Vous envoyer des confirmations, rappels et notifications importantes</li>
                <li>Améliorer l’expérience utilisateur et le fonctionnement du site</li>
                <li>Répondre à vos demandes via le support</li>
            </ul>

            <div class="highlight">
                <strong>Nous ne vendons jamais vos données à des tiers.</strong><br>
                Elles ne sont partagées qu’avec des prestataires techniques strictement encadrés (paiement, livraison, hébergement).
            </div>

            <h2>3. Durée de conservation</h2>
            <p>Vos données sont conservées :</p>
            <ul>
                <li>Tant que votre compte est actif (vous pouvez le supprimer à tout moment)</li>
                <li>5 ans après la dernière activité pour les données comptables et légales</li>
                <li>Les messages de contact sont conservés 2 ans maximum</li>
            </ul>

            <h2>4. Vos droits</h2>
            <p>Conformément à la loi 09-08 au Maroc et au RGPD (pour les résidents UE), vous disposez des droits suivants :</p>
            <ul>
                <li>Accéder à vos données personnelles</li>
                <li>Les rectifier si elles sont inexactes</li>
                <li>Demander leur suppression (sauf obligations légales)</li>
                <li>Vous opposer à certains traitements</li>
                <li>Retirer votre consentement quand il s’applique</li>
            </ul>
            <p>Pour exercer ces droits, écrivez-nous à : <strong>support@bibliotech.ma</strong></p>

            <h2>5. Sécurité de vos données</h2>
            <p>Nous utilisons des mesures techniques et organisationnelles avancées :</p>
            <ul>
                <li>Connexion sécurisée HTTPS partout</li>
                <li>Chiffrement des données sensibles</li>
                <li>Hébergement sécurisé au Maroc/Europe</li>
                <li>Contrôles d’accès stricts</li>
            </ul>

            <h2>6. Cookies et traceurs</h2>
            <p>Nous utilisons des cookies essentiels pour le fonctionnement du site (panier, connexion).<br>
            Aucun cookie publicitaire ou de suivi tiers n’est installé sans votre consentement préalable.</p>

            <h2>7. Modifications de la politique</h2>
            <p>Nous pouvons mettre à jour cette politique. Vous serez informé(e) par e-mail ou notification sur le site en cas de changement important.</p>

            <p class="last-update">Dernière mise à jour : 7 février 2026</p>
        </div>

        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Retour à l’accueil
        </a>
    </div>

<?php require_once 'footer.php'; ?>
</body>
</html>