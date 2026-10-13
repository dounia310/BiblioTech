<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --primary-dark: #152a45;
            --accent: #e8b86d;
            --accent-hover: #d4a55c;
            --secondary: #3d7a8c;
            --dark: #0f172a;
            --light: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            
            /* Rayons et transitions */
            --radius-sm: 6px;
            --radius: 10px;
            --radius-md: 14px;
            --radius-full: 9999px;
            --transition: 250ms ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        /* ===== FOOTER PRINCIPAL ===== */
        .main-footer {
            background: linear-gradient(180deg, var(--gray-800) 0%, var(--gray-900) 100%);
            color: var(--gray-300);
            padding: 70px 0 0;
            position: relative;
        }

        /* Décoration subtile en haut */
        .main-footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--accent) 50%, var(--primary) 100%);
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1.2fr;
            gap: 50px;
        }

        /* Sections du footer */
        .footer-section {
            position: relative;
        }

        .footer-section.brand {
            padding-right: 20px;
        }

        /* Logo dans le footer */
        .footer-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            margin-bottom: 20px;
        }

        .footer-logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .footer-logo-icon i {
            font-size: 1.4rem;
            color: white;
        }

        .footer-logo-text {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: white;
        }

        .footer-logo-text span {
            color: var(--accent);
        }

        .footer-description {
            font-size: 0.95rem;
            line-height: 1.8;
            color: var(--gray-400);
            margin-bottom: 25px;
        }

        /* Titres des sections */
        .footer-section h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 24px;
            color: white;
            position: relative;
            padding-bottom: 12px;
        }

        .footer-section h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--accent);
            border-radius: 2px;
        }

        /* Liens du footer */
        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 14px;
        }

        .footer-links a {
            color: var(--gray-400);
            text-decoration: none;
            transition: all var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        }

        .footer-links a::before {
            content: '\f054';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 0.7rem;
            color: var(--accent);
            opacity: 0;
            transform: translateX(-5px);
            transition: all var(--transition);
        }

        .footer-links a:hover {
            color: white;
            padding-left: 5px;
        }

        .footer-links a:hover::before {
            opacity: 1;
            transform: translateX(0);
        }

        /* Contact info */
        .footer-contact {
            list-style: none;
        }

        .footer-contact li {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 18px;
            color: var(--gray-400);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .footer-contact li i {
            color: var(--accent);
            font-size: 1rem;
            margin-top: 3px;
            width: 20px;
            text-align: center;
        }

        /* Réseaux sociaux */
        .social-links {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: var(--radius);
            color: var(--gray-300);
            text-decoration: none;
            transition: all var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .social-link:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-3px);
        }

        /* Méthodes de paiement */
        .payment-section {
            margin-top: 25px;
        }

        .payment-title {
            font-size: 0.85rem;
            color: var(--gray-500);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .payment-methods {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .payment-method {
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all var(--transition);
        }

        .payment-method:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--accent);
        }

        .payment-method i {
            font-size: 1rem;
        }

        /* Newsletter (optionnel) */
        .newsletter-form {
            margin-top: 20px;
        }

        .newsletter-input-group {
            display: flex;
            background: rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-full);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .newsletter-input {
            flex: 1;
            padding: 12px 18px;
            border: none;
            background: transparent;
            color: white;
            font-size: 0.9rem;
        }

        .newsletter-input::placeholder {
            color: var(--gray-500);
        }

        .newsletter-input:focus {
            outline: none;
        }

        .newsletter-btn {
            background: var(--accent);
            color: var(--primary-dark);
            border: none;
            padding: 0 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all var(--transition);
        }

        .newsletter-btn:hover {
            background: var(--accent-hover);
        }

        /* Footer bottom */
        .footer-bottom {
            background: rgba(0, 0, 0, 0.3);
            padding: 25px 0;
            margin-top: 60px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .footer-bottom .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .footer-copyright {
            color: var(--gray-500);
            font-size: 0.9rem;
        }

        .footer-copyright strong {
            color: var(--accent);
        }

        .footer-legal {
            display: flex;
            gap: 25px;
        }

        .footer-legal a {
            color: var(--gray-500);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color var(--transition);
        }

        .footer-legal a:hover {
            color: var(--accent);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) {
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
                gap: 40px;
            }
            
            .footer-section.brand {
                grid-column: span 2;
                padding-right: 0;
            }
        }

        @media (max-width: 768px) {
            .main-footer {
                padding: 50px 0 0;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                gap: 35px;
            }
            
            .footer-section.brand {
                grid-column: span 1;
            }
            
            .footer-bottom .container {
                flex-direction: column;
                text-align: center;
            }
            
            .footer-legal {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<!-- footer.php -->
<footer class="main-footer">
    <div class="footer-content">
        <!-- Section Marque -->
        <div class="footer-section brand">
            <a href="index.php" class="footer-logo">
                <div class="footer-logo-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="footer-logo-text">Biblio<span>tech</span></div>
            </a>
            <p class="footer-description">
                Votre bibliothèque en ligne de référence. Empruntez, achetez et découvrez des milliers de livres 
                dans tous les genres. Une expérience de lecture moderne et accessible à tous.
            </p>
            <div class="social-links">
                <a href="#" class="social-link" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="social-link" title="Twitter"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-link" title="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="#" class="social-link" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                <a href="#" class="social-link" title="YouTube"><i class="fab fa-youtube"></i></a>
            </div>
        </div>
        
        <!-- Liens rapides -->
        <div class="footer-section">
            <h3>Liens rapides</h3>
            <ul class="footer-links">
                <li><a href="index.php"><i class="fas fa-chevron-right"></i> Accueil</a></li>
                <li><a href="catalogue.php"><i class="fas fa-chevron-right"></i> Catalogue</a></li>
                <li><a href="actualites.php"><i class="fas fa-chevron-right"></i> Actualités</a></li>
                <li><a href="evenements.php"><i class="fas fa-chevron-right"></i> Événements</a></li>
                <li><a href="contact.php"><i class="fas fa-chevron-right"></i> Contact</a></li>
            </ul>
        </div>
        
        <!-- Informations -->
        <div class="footer-section">
            <h3>Informations</h3>
            <ul class="footer-links">
                <li><a href="aide.php"><i class="fas fa-chevron-right"></i> Aide</a></li>
                <li><a href="faq.php"><i class="fas fa-chevron-right"></i> FAQ</a></li>
                <li><a href="conditions.php"><i class="fas fa-chevron-right"></i> Conditions d'utilisation</a></li>
                <li><a href="confidentialite.php"><i class="fas fa-chevron-right"></i> Politique de confidentialité</a></li>
                
            </ul>
        </div>
        
        <!-- Contact – mêmes infos que le header + adresse Marrakech -->
        <div class="footer-section">
            <h3>Contactez-nous</h3>
            <ul class="footer-contact">
                <li>
                    <i class="fas fa-map-marker-alt"></i>
                    <span>456 Avenue Mohammed V, Guéliz, Marrakech, Maroc</span>
                </li>
                <li>
                    <i class="fas fa-phone-alt"></i>
                    <span>+212 6 234 567 89</span>
                </li>
                <li>
    <i class="fas fa-envelope"></i>
    <a href="mailto:contact@bibliotech.ma">contact@bibliotech.ma</a>
</li>
                <li>
                    <i class="fas fa-clock"></i>
                    <span>Lun-Sam: 9h-19h</span>
                </li>
            </ul>
            
    </div>
    
    <!-- Bas de footer -->
    <div class="footer-bottom">
        <div class="container">
            <p class="footer-copyright">
                © <?= date('Y') ?> <strong>Bibliotech</strong>. Tous droits réservés.
            </p>
            <div class="footer-legal">
                <a href="mentions-legales.php">Mentions légales</a>
                <a href="cookies.php">Cookies</a>
                <a href="accessibilite.php">Accessibilité</a>
            </div>
        </div>
    </div>
</footer>