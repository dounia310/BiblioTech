<?php
// TOUT EN HAUT – AUCUN espace, saut de ligne ou texte avant cette ligne !
session_start();

// Configuration de la base de données (à adapter selon votre configuration)
require_once 'connexion.php';

// Optionnel : vérif rapide (à enlever en prod)
if (!isset($pdo)) {
    error_log("Erreur : connexion PDO non disponible");
}

// Récupérer le nombre d'articles dans le panier
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

// Déterminer la page active
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="favicon.svg" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png" />
    <link rel="manifest" href="site.webmanifest" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --primary-dark: #152a45;
            --accent: #e8b86d;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-500: #64748b;
            --gray-600: #475569;
            --light: #ffffff;
            
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            
            --radius: 10px;
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
            color: var(--dark);
            line-height: 1.6;
            background: var(--gray-100);
        }

        /* Barre supérieure */
        .header-topbar {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            color: white;
            padding: 10px 0;
            font-size: 0.875rem;
        }

        .header-topbar .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar-info {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .topbar-info span {
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0.9;
        }

        .topbar-info i {
            color: var(--accent);
        }

        .topbar-links {
            display: flex;
            gap: 20px;
        }

        .topbar-links a {
            color: white;
            text-decoration: none;
            opacity: 0.85;
            transition: opacity var(--transition);
        }

        .topbar-links a:hover {
            opacity: 1;
            color: var(--accent);
        }

        /* Header principal - MODIFIÉ POUR MENU CENTRÉ */
        .main-header {
            background: var(--light);
            border-bottom: 1px solid var(--gray-200);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-md);
        }

        .header-main {
            padding: 15px 0;
        }

        .header-main .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
        }

        /* Logo - GAUCHE */
        .logo-container {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
        }

        .logo-img {
            height: 50px;
            width: auto;
            max-width: 160px;
            object-fit: contain;
        }

        /* Fallback si image manquante */
        .logo-fallback {
            display: none;
            height: 50px;
            padding: 0 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: var(--radius);
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .logo-fallback i {
            color: var(--accent);
            font-size: 1.8rem;
        }

        /* Barre de recherche réduite à une icône */
        .search-icon-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: var(--gray-100);
            border: none;
            border-radius: var(--radius-full);
            color: var(--gray-600);
            font-size: 1.1rem;
            cursor: pointer;
            transition: all var(--transition);
            text-decoration: none;
        }

        .search-icon-btn:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.05);
        }

        /* Zone centrale pour le menu principal */
        .header-center {
            flex: 1;
            display: flex;
            justify-content: center;
        }

        /* Menu principal centré */
        .main-nav {
            display: flex;
            justify-content: center;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 8px;
            align-items: center;
        }

        .nav-link {
            color: var(--gray-600);
            text-decoration: none;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all var(--transition);
            position: relative;
            border-radius: var(--radius);
            white-space: nowrap;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary);
            background: var(--gray-100);
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 4px;
            left: 20px;
            right: 20px;
            height: 2px;
            background: var(--accent);
            transform: scaleX(0);
            transition: transform var(--transition);
        }

        .nav-link:hover::after, .nav-link.active::after {
            transform: scaleX(1);
        }

        .nav-badge {
            background: var(--accent);
            color: var(--primary-dark);
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: var(--radius-full);
            margin-left: 6px;
        }

        /* Actions utilisateur - DROITE */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
            position: relative;
        }

        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            text-decoration: none;
            color: var(--gray-600);
            font-size: 1.1rem;
            border-radius: var(--radius-full);
            transition: all var(--transition);
            position: relative;
        }

        .action-btn:hover {
            background: var(--gray-100);
            color: var(--primary);
        }

        .cart-count {
            position: absolute;
            top: -2px;
            right: -2px;
            background: var(--accent);
            color: var(--primary-dark);
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: var(--radius-full);
            min-width: 18px;
            text-align: center;
        }

        /* Bouton utilisateur avec menu déroulant */
        .user-menu-container {
            position: relative;
        }

        .user-menu-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: var(--gray-100);
            border: none;
            border-radius: var(--radius-full);
            color: var(--gray-600);
            font-size: 1.1rem;
            cursor: pointer;
            transition: all var(--transition);
        }

        .user-menu-btn:hover {
            background: var(--primary);
            color: white;
        }

        /* Menu déroulant utilisateur */
        .user-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 10px;
            min-width: 220px;
            background: var(--light);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-200);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all var(--transition);
            z-index: 1001;
            overflow: hidden;
        }

        .user-dropdown-menu.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .user-dropdown-header {
            padding: 16px 20px;
            background: var(--gray-100);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.95rem;
        }

        .user-email {
            font-size: 0.8rem;
            color: var(--gray-500);
            margin-top: 2px;
        }

        .user-dropdown-links {
            padding: 8px 0;
        }

        .user-dropdown-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--gray-600);
            text-decoration: none;
            transition: all var(--transition);
            font-size: 0.9rem;
        }

        .user-dropdown-link:hover {
            background: var(--gray-100);
            color: var(--primary);
            padding-left: 24px;
        }

        .user-dropdown-link i {
            width: 20px;
            text-align: center;
        }

        .user-dropdown-divider {
            height: 1px;
            background: var(--gray-200);
            margin: 8px 0;
        }

        .logout-link {
            color: #dc2626 !important;
        }

        .logout-link:hover {
            background: #fef2f2 !important;
            color: #dc2626 !important;
        }

        .login-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 10px 20px;
            border-radius: var(--radius-full);
            font-weight: 600;
            box-shadow: var(--shadow-md);
            transition: all var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Menu mobile */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary);
            cursor: pointer;
            padding: 10px;
            width: 44px;
            height: 44px;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-full);
        }

        .mobile-menu-btn:hover {
            background: var(--gray-100);
        }

        /* Barre de recherche mobile */
        .search-bar-mobile {
            display: none;
            padding: 15px 24px;
            background: var(--light);
            border-top: 1px solid var(--gray-200);
        }

        .search-form-mobile {
            display: flex;
            background: var(--gray-100);
            border-radius: var(--radius-full);
            overflow: hidden;
            border: 2px solid transparent;
            transition: all var(--transition);
        }

        .search-form-mobile:focus-within {
            background: var(--light);
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(232, 184, 109, 0.15);
        }

        .search-input-mobile {
            flex: 1;
            padding: 14px 22px;
            border: none;
            background: transparent;
            font-size: 0.95rem;
        }

        .search-input-mobile:focus {
            outline: none;
        }

        .search-btn-mobile {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0 28px;
            cursor: pointer;
            font-size: 1.1rem;
            transition: all var(--transition);
        }

        .search-btn-mobile:hover {
            background: var(--primary-light);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .logo-img {
                height: 45px;
                max-width: 140px;
            }
            
            .nav-link {
                padding: 10px 16px;
                font-size: 0.9rem;
            }
            
            .nav-link i {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 992px) {
            .header-topbar {
                display: none;
            }
            
            .header-center {
                display: none;
            }
            
            .mobile-menu-btn {
                display: flex;
            }
            
            .search-bar-mobile {
                display: block;
            }
            
            .header-main .container {
                gap: 20px;
            }
            
            .search-icon-btn {
                display: none;
            }
            
            /* Ajustement du menu utilisateur sur mobile */
            .user-dropdown-menu {
                position: fixed;
                top: 0;
                right: -100%;
                width: 300px;
                height: 100vh;
                margin: 0;
                border-radius: 0;
                border-left: 1px solid var(--gray-200);
                transform: translateX(0);
                transition: right var(--transition);
                box-shadow: var(--shadow-lg);
            }
            
            .user-dropdown-menu.active {
                right: 0;
            }
            
            .user-dropdown-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1000;
            }
            
            .user-dropdown-overlay.active {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .logo-img {
                height: 40px;
                max-width: 120px;
            }
            
            .login-btn span {
                display: none;
            }
            
            .login-btn {
                padding: 10px;
                width: 44px;
                height: 44px;
                justify-content: center;
            }
            
            .header-actions {
                gap: 8px;
            }
        }

        @media (max-width: 576px) {
            .logo-img {
                height: 35px;
                max-width: 100px;
            }
            
            .header-main .container {
                padding: 0 16px;
            }
            
            .search-bar-mobile {
                padding: 15px 16px;
            }
            
            .user-dropdown-menu {
                width: 280px;
            }
        }

        /* Animation pour le panier */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .cart-count.new {
            animation: pulse 0.5s ease;
        }

        /* Animation pour le menu déroulant */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>

    <!-- Barre supérieure -->
    <div class="header-topbar">
        <div class="container">
            <div class="topbar-info">
                <span><i class="fas fa-phone-alt"></i> +212 6 234 567 89</span>
                <span><i class="fas fa-envelope"></i> contact@bibliotech.ma</span>
                <span><i class="fas fa-clock"></i> Lun-Sam: 9h-19h</span>
            </div>
            <div class="topbar-links">
                <a href="aide.php"><i class="fas fa-question-circle"></i> Aide</a>
                <a href="faq.php"><i class="fas fa-info-circle"></i> FAQ</a>
            </div>
        </div>
    </div>

    <!-- Header principal -->
    <header class="main-header">
        <div class="header-main">
            <div class="container">
                
                <!-- LOGO - GAUCHE -->
                <a href="index.php" class="logo-container">
                    <img src="./logo_bibliotech.png" 
                         alt="Bibliotech" 
                         class="logo-img"
                         onerror="this.style.display='none'; this.parentElement.querySelector('.logo-fallback').style.display='flex';">
                    
                    <!-- Fallback si image manquante -->
                    <div class="logo-fallback">
                        <i class="fas fa-book"></i>
                        <span>BiblioTech</span>
                    </div>
                </a>
                
                <!-- Zone centrale avec menu principal -->
                <div class="header-center">
                    <nav class="main-nav">
                        <ul class="nav-links">
                            <li><a href="index.php" class="nav-link <?php echo $currentPage == 'index.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Accueil</a></li>
                            <li><a href="catalogue.php" class="nav-link <?php echo $currentPage == 'catalogue.php' ? 'active' : ''; ?>"><i class="fas fa-book"></i> Catalogue</a></li>
                            <li><a href="actualites.php" class="nav-link <?php echo $currentPage == 'actualites.php' ? 'active' : ''; ?>"><i class="fas fa-newspaper"></i> Actualités</a></li>
                            <li><a href="evenements.php" class="nav-link <?php echo $currentPage == 'evenements.php' ? 'active' : ''; ?>"><i class="fas fa-calendar"></i> Événements</a></li>
                            <li><a href="contact.php" class="nav-link <?php echo $currentPage == 'contact.php' ? 'active' : ''; ?>"><i class="fas fa-envelope"></i> Contact</a></li>
                        </ul>
                    </nav>
                </div>
                
                <!-- Actions - DROITE -->
<div class="header-actions">
    <!-- Barre de recherche réduite à une icône -->
    <a href="recherche.php" class="search-icon-btn" title="Rechercher">
        <i class="fas fa-search"></i>
    </a>
    
    <!-- Panier (caché pour admin si tu veux – ici je le laisse visible) -->
    <a href="panier.php" class="action-btn cart-btn" title="Panier">
        <i class="fas fa-shopping-cart"></i>
        <?php if ($cartCount > 0): ?>
            <span class="cart-count" id="cartCounter"><?php echo $cartCount; ?></span>
        <?php endif; ?>
    </a>
    
    <!-- Menu utilisateur avec déroulant -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="user-menu-container">
            <button class="user-menu-btn" id="userMenuBtn" title="Compte">
                <i class="fas fa-user-circle"></i>
            </button>
            
            <!-- Menu déroulant utilisateur -->
            <div class="user-dropdown-menu" id="userDropdownMenu">
                <div class="user-dropdown-header">
                    <div class="user-avatar">
                        <?php 
                            $username = $_SESSION['username'] ?? 'Utilisateur';
                            echo strtoupper(substr($username, 0, 1)); 
                        ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name">
                            <?php echo htmlspecialchars($username); ?>
                        </div>
                        <div class="user-email">
                            <?php echo htmlspecialchars($_SESSION['email'] ?? '—'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="user-dropdown-links">
                    <?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'): ?>
                        <!-- Liens réservés aux membres normaux -->
                        <a href="membre_compte.php" class="user-dropdown-link">
                            <i class="fas fa-user"></i>
                            <span>Mon profil</span>
                        </a>
                        <a href="mes_emprunts.php" class="user-dropdown-link">
                            <i class="fas fa-book"></i>
                            <span>Mes emprunts</span>
                        </a>
                        <a href="mes_reservations.php" class="user-dropdown-link">
                            <i class="fas fa-clock"></i>
                            <span>Mes réservations</span>
                        </a>
                        <a href="mes_amendes.php" class="user-dropdown-link">
                            <i class="fas fa-money-bill"></i>
                            <span>Mes amendes</span>
                        </a>
                        <?php if (file_exists('mes_achats.php')): ?>
                        <a href="mes_achats.php" class="user-dropdown-link">
                            <i class="fas fa-shopping-bag"></i>
                            <span>Mes achats</span>
                        </a>
                        <?php endif; ?>
                        <div class="user-dropdown-divider"></div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <!-- Seulement pour admin -->
                        <a href="admin_dashboard.php" class="user-dropdown-link" style="color: var(--primary); font-weight: 600;">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Administration</span>
                        </a>
                        <div class="user-dropdown-divider"></div>
                    <?php endif; ?>
                    
                    <a href="logout.php" class="user-dropdown-link logout-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Déconnexion</span>
                    </a>
                </div>
            </div>
            
            <!-- Overlay pour mobile -->
            <div class="user-dropdown-overlay" id="userDropdownOverlay"></div>
        </div>
    <?php else: ?>
        <a href="login.php" class="login-btn">
            <i class="fas fa-sign-in-alt"></i>
            <span>Connexion</span>
        </a>
    <?php endif; ?>
    
    <!-- Menu mobile -->
    <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
        <i class="fas fa-bars"></i>
    </button>
</div>
        
        <!-- Barre de recherche mobile (affichée seulement sur mobile) -->
        <div class="search-bar-mobile" id="mobileSearchBar">
            <form action="recherche.php" method="GET" class="search-form-mobile">
                <input type="text" name="q" placeholder="Rechercher un livre, un auteur..." class="search-input-mobile">
                <button type="submit" class="search-btn-mobile">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </header>

    <script>
        // Fonction pour le menu mobile
        function toggleMobileMenu() {
            const searchBar = document.getElementById('mobileSearchBar');
            searchBar.style.display = searchBar.style.display === 'block' ? 'none' : 'block';
        }
        
        // Fonction pour le menu utilisateur déroulant
        function toggleUserMenu() {
            const userMenu = document.getElementById('userDropdownMenu');
            const overlay = document.getElementById('userDropdownOverlay');
            
            userMenu.classList.toggle('active');
            if (overlay) {
                overlay.classList.toggle('active');
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Animation pour le panier
            const cartCounter = document.getElementById('cartCounter');
            if (cartCounter) {
                cartCounter.classList.add('new');
                setTimeout(() => {
                    cartCounter.classList.remove('new');
                }, 500);
            }
            
            // Gestion du menu utilisateur
            const userMenuBtn = document.getElementById('userMenuBtn');
            if (userMenuBtn) {
                userMenuBtn.addEventListener('click', toggleUserMenu);
                
                // Fermer le menu en cliquant sur l'overlay (mobile)
                const overlay = document.getElementById('userDropdownOverlay');
                if (overlay) {
                    overlay.addEventListener('click', toggleUserMenu);
                }
                
                // Fermer le menu en cliquant à l'extérieur (desktop)
                document.addEventListener('click', function(event) {
                    const userMenu = document.getElementById('userDropdownMenu');
                    if (userMenu && userMenu.classList.contains('active') && 
                        !userMenu.contains(event.target) && 
                        !userMenuBtn.contains(event.target)) {
                        toggleUserMenu();
                    }
                });
            }
            
            // Fermer la barre de recherche mobile en cliquant à l'extérieur
            document.addEventListener('click', function(event) {
                const searchBar = document.getElementById('mobileSearchBar');
                const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
                
                if (searchBar && searchBar.style.display === 'block' && 
                    !searchBar.contains(event.target) && 
                    !mobileMenuBtn.contains(event.target)) {
                    searchBar.style.display = 'none';
                }
            });
            
            // Fermer le menu utilisateur quand on clique sur un lien
            document.querySelectorAll('.user-dropdown-link').forEach(link => {
                link.addEventListener('click', function() {
                    const userMenu = document.getElementById('userDropdownMenu');
                    const overlay = document.getElementById('userDropdownOverlay');
                    
                    if (userMenu && userMenu.classList.contains('active')) {
                        userMenu.classList.remove('active');
                        if (overlay) {
                            overlay.classList.remove('active');
                        }
                    }
                });
            });
        });
        
        // Fermer le menu utilisateur avec la touche ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const userMenu = document.getElementById('userDropdownMenu');
                const overlay = document.getElementById('userDropdownOverlay');
                
                if (userMenu && userMenu.classList.contains('active')) {
                    userMenu.classList.remove('active');
                    if (overlay) {
                        overlay.classList.remove('active');
                    }
                }
            }
        });
    </script>
</body>
</html>