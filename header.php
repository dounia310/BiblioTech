<?php
// TOUT EN HAUT – AUCUN espace, saut de ligne ou texte avant cette ligne !
session_start();
require_once 'connexion.php';

// Optionnel : vérif rapide (à enlever en prod)
if (!isset($pdo)) {
    die("Erreur : connexion PDO non disponible");
}

// Récupérer le nombre d'articles dans le panier
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        /* Header principal */
        .header-main {
            padding: 15px 0;
            background: var(--light);
            border-bottom: 1px solid var(--gray-200);
        }

        .header-main .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 40px;
        }

        /* ===== LOGO IMAGE ===== */
            
        .logo-img[src$=".png"],
        .logo-img[src*=".png"] {
            /* Force encore plus la transparence */
            background: none !important;
            background-color: none !important;
            -webkit-mask-image: none;
            mask-image: none;
        }

        /* Fallback si image manquante */
        .logo-fallback {
            display: none;
            height: 70px;
            padding: 0 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: var(--radius);
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .logo-fallback i {
            color: var(--accent);
            font-size: 2rem;
        }

        /* Barre de recherche */
        .search-bar {
            flex: 1;
            max-width: 550px;
        }

        .search-form {
            display: flex;
            background: var(--gray-100);
            border-radius: var(--radius-full);
            overflow: hidden;
            border: 2px solid transparent;
            transition: all var(--transition);
        }

        .search-form:focus-within {
            background: var(--light);
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(232, 184, 109, 0.15);
        }

        .search-input {
            flex: 1;
            padding: 14px 22px;
            border: none;
            background: transparent;
            font-size: 0.95rem;
        }

        .search-input:focus {
            outline: none;
        }

        .search-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0 28px;
            cursor: pointer;
            font-size: 1.1rem;
            transition: all var(--transition);
        }

        .search-btn:hover {
            background: var(--primary-light);
        }

        /* Actions utilisateur */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--gray-600);
            font-weight: 500;
            font-size: 0.9rem;
            padding: 10px 16px;
            border-radius: var(--radius);
            transition: all var(--transition);
            position: relative;
        }

        .action-btn:hover {
            background: var(--gray-100);
            color: var(--primary);
        }

        .action-btn.cart-btn {
            background: var(--gray-100);
            padding-right: 20px;
        }

        .action-btn.cart-btn:hover {
            background: var(--primary);
            color: white;
        }

        .cart-count {
            position: absolute;
            top: 2px;
            right: 8px;
            background: var(--accent);
            color: var(--primary-dark);
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: var(--radius-full);
            min-width: 18px;
            text-align: center;
        }

        .login-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 12px 24px;
            border-radius: var(--radius-full);
            font-weight: 600;
            box-shadow: var(--shadow-md);
            transition: all var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .logout-btn {
            background: transparent;
            color: var(--gray-600);
            border: 2px solid var(--gray-200);
            padding: 10px 20px;
            border-radius: var(--radius-full);
            font-weight: 500;
            transition: all var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn:hover {
            border-color: var(--error);
            color: var(--error);
        }

        /* Navigation */
        .main-nav {
            background: var(--light);
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 1px solid var(--gray-200);
            box-shadow: var(--shadow-md);
        }

        .main-nav .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 4px;
        }

        .nav-link {
            color: var(--gray-600);
            text-decoration: none;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all var(--transition);
            position: relative;
            border-radius: var(--radius);
            margin: 4px 0;
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

        /* Mobile */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary);
            cursor: pointer;
            padding: 10px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .logo-img {
                height: 60px;
                max-width: 240px;
            }
            .action-btn span {
                display: none;
            }
        }

        @media (max-width: 992px) {
            .header-topbar {
                display: none;
            }
            .header-main .container {
                flex-wrap: wrap;
            }
            .search-bar {
                order: 3;
                max-width: 100%;
                width: 100%;
            }
            .mobile-menu-btn {
                display: block;
            }
            .main-nav {
                display: none;
            }
            .main-nav.active {
                display: block;
            }
            .nav-links {
                flex-direction: column;
            }
        }

        @media (max-width: 576px) {
            .logo-img {
                height: 50px;
                max-width: 200px;
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
                <a href="aide.php">Aide</a>
                <a href="faq.php">FAQ</a>
            </div>
        </div>
    </div>

    <!-- Header principal -->
    <header class="main-header">
        <div class="header-main">
            <div class="container">
                
                <!-- LOGO -->
                <a href="index.php" class="logo-container">
                    <img src="./logo Bibliotech (4).png" 
                         alt="Bibliotech" 
                         class="logo-img"
                         onerror="this.style.display='none'; this.parentElement.querySelector('.logo-fallback').style.display='flex';">
                    
                    <!-- Fallback si image manquante -->
                    <div class="logo-fallback">
                        <i class="fas fa-book"></i>
                        <span>BiblioTech</span>
                    </div>
                </a>
                
                <!-- Barre de recherche -->
                <div class="search-bar">
                    <form action="recherche.php" method="GET" class="search-form">
                        <input type="text" name="q" placeholder="Rechercher un livre, un auteur..." class="search-input">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                
                <!-- Actions -->
                <div class="header-actions">
                    <a href="panier.php" class="action-btn cart-btn" title="Panier">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if ($cartCount > 0): ?>
                            <span class="cart-count"><?php echo $cartCount; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="membre_compte.php" class="action-btn" title="Mon compte">
                            <i class="fas fa-user-circle"></i>
                        </a>
                        <a href="logout.php" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="login-btn">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Connexion</span>
                        </a>
                    <?php endif; ?>
                    
                    <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Navigation -->
    <nav class="main-nav" id="mainNav">
        <div class="container">
            <ul class="nav-links">
                <li><a href="index.php" class="nav-link active"><i class="fas fa-home"></i> Accueil</a></li>
                <li><a href="catalogue.php" class="nav-link"><i class="fas fa-book"></i> Catalogue</a></li>
                <li><a href="nouveautes.php" class="nav-link"><i class="fas fa-star"></i> Nouveautés <span class="nav-badge">New</span></a></li>
                <li><a href="promotions.php" class="nav-link"><i class="fas fa-tag"></i> Promos</a></li>
                <li><a href="evenements.php" class="nav-link"><i class="fas fa-calendar"></i> Événements</a></li>
                <li><a href="contact.php" class="nav-link"><i class="fas fa-envelope"></i> Contact</a></li>
            </ul>
        </div>
    </nav>

    <script>
        function toggleMobileMenu() {
            document.getElementById('mainNav').classList.toggle('active');
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop() || 'index.php';
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>