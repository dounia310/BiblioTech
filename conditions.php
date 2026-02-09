<?php
require_once 'connexion.php'; // si besoin pour header/footer
require_once 'header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conditions d'utilisation – Bibliotech</title>
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

        .terms-content {
            background: white;
            padding: 3rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            margin-bottom: 4rem;
        }

        .terms-content h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            color: var(--primary);
            margin: 2.5rem 0 1.2rem;
            border-bottom: 2px solid var(--accent);
            padding-bottom: 0.6rem;
        }

        .terms-content h3 {
            font-size: 1.5rem;
            margin: 2rem 0 1rem;
            color: var(--primary-dark);
        }

        .terms-content p {
            margin-bottom: 1.2rem;
            color: var(--gray-700);
        }

        .terms-content ul {
            list-style: none;
            padding-left: 0;
            margin-bottom: 1.5rem;
        }

        .terms-content ul li {
            padding: 0.8rem 0;
            position: relative;
            padding-left: 2rem;
        }

        .terms-content ul li::before {
            content: '\f058';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            color: var(--success);
            position: absolute;
            left: 0;
            top: 0.9rem;
        }

        .terms-content .highlight {
            background: var(--gray-50);
            padding: 1.2rem;
            border-radius: 12px;
            border-left: 5px solid var(--accent);
            margin: 1.5rem 0;
            font-weight: 500;
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
            .terms-content { padding: 2rem; }
        }
    </style>
</head>
<body>

    <section class="page-hero">
        <div class="container">
            <h1>Conditions Générales d'Utilisation</h1>
            <p>En utilisant Bibliotech, vous acceptez les conditions suivantes. Merci de les lire attentivement.</p>
        </div>
    </section>

    <div class="container">
        <div class="terms-content">
            <h2>1. Objet et acceptation</h2>
            <p>Les présentes Conditions Générales d'Utilisation (CGU) régissent l'utilisation du site Bibliotech (ci-après « le Site ») et des services associés (emprunt, achat, réservation de livres, espace membre, etc.).</p>
            <p>En accédant ou en utilisant le Site, vous acceptez sans réserve ces CGU. Si vous n’êtes pas d’accord, veuillez ne pas utiliser nos services.</p>

            <h2>2. Inscription et compte membre</h2>
            <p>Pour accéder à certains services (emprunt, achat, réservation), vous devez créer un compte membre.</p>
            <ul>
                <li>Vous vous engagez à fournir des informations exactes, à jour et complètes.</li>
                <li>Vous êtes responsable de la confidentialité de votre mot de passe.</li>
                <li>Toute utilisation frauduleuse ou abusive de votre compte peut entraîner sa suspension immédiate.</li>
            </ul>

            <h2>3. Emprunt de livres</h2>
            <ul>
                <li>Les emprunts sont réservés aux membres actifs.</li>
                <li>Durée maximale : 21 jours (prolongeable deux fois sous réserve de disponibilité).</li>
                <li>Retard : 5 DH par jour de retard après la date limite.</li>
                <li>Perte ou détérioration : remboursement du prix du livre + 100 DH de frais administratifs.</li>
                <li>En cas de retard supérieur à 30 jours, votre compte sera suspendu jusqu’au règlement.</li>
            </ul>

            <h2>4. Achat de livres</h2>
            <ul>
                <li>Les prix sont indiqués en dirhams marocains (DH), toutes taxes comprises.</li>
                <li>Paiement sécurisé via CMI (cartes bancaires) ou virement.</li>
                <li>Livraison : 24–72h au Maroc (gratuite dès 300 DH d’achat).</li>
                <li>Retours possibles sous 14 jours (livre intact, emballage d’origine).</li>
            </ul>

            <div class="highlight">
                <strong>Offre spéciale :</strong> Pour tout achat d’au moins 200 DH, vous bénéficiez d’<strong>1 emprunt gratuit</strong> (au choix parmi les livres disponibles à l’instant T).
            </div>

            <h2>5. Responsabilités de l'utilisateur</h2>
            <ul>
                <li>Respecter les droits d’auteur et ne pas copier/distribuer illégalement les contenus.</li>
                <li>Ne pas utiliser le Site à des fins commerciales sans autorisation.</li>
                <li>Ne pas diffuser de contenus illicites, violents, diffamatoires ou discriminatoires.</li>
            </ul>

            <h2>6. Propriété intellectuelle</h2>
            <p>Le Site, son contenu (textes, photos, design, logo) et les fiches livres sont protégés par le droit d’auteur et appartiennent à Bibliotech ou à ses partenaires. Toute reproduction ou utilisation sans autorisation est interdite.</p>

            <h2>7. Limitation de responsabilité</h2>
            <p>Bibliotech ne peut être tenu responsable des interruptions techniques, erreurs de disponibilité ou dommages indirects liés à l’utilisation du Site.</p>

            <h2>8. Droit applicable et litiges</h2>
            <p>Les présentes CGU sont régies par le droit marocain. Tout litige sera soumis aux tribunaux compétents de Marrakech.</p>

            <h2>9. Modification des CGU</h2>
            <p>Nous pouvons modifier ces conditions à tout moment. Vous serez informé(e) par e-mail ou notification sur le Site en cas de changement important.</p>

            <p class="last-update">Dernière mise à jour : 7 février 2026</p>
        </div>

        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Retour à l’accueil
        </a>
    </div>

<?php require_once 'footer.php'; ?>
</body>
</html>