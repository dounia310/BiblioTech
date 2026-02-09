<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'connexion.php';
require_once 'header.php';

// Statistiques
$stats = [
    'livres'       => $pdo->query("SELECT COUNT(*) FROM livres")->fetchColumn() ?: 0,
    'disponibles'  => $pdo->query("SELECT COUNT(*) FROM exemplaires WHERE statut = 'disponible'")->fetchColumn() ?: 0,
    'membres'      => $pdo->query("SELECT COUNT(*) FROM membres WHERE statut = 'actif'")->fetchColumn() ?: 0,
    'emprunts'     => $pdo->query("SELECT COUNT(*) FROM emprunts WHERE date_retour_effective IS NULL")->fetchColumn() ?: 0,
];

// Livres populaires (8 au hasard parmi les plus populaires)
$livres_populaires = $pdo->query("
    SELECT l.id, l.titre, a.nom_complet AS auteur, l.couverture_url, l.note_moyenne, l.prix,
           (SELECT COUNT(*) FROM exemplaires e WHERE e.livre_id = l.id AND e.statut = 'disponible') as dispo
    FROM livres l
    LEFT JOIN auteurs a ON l.auteur_id = a.id
    ORDER BY l.popularite DESC, RAND()
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC) ?? [];

// Actualités (nouvelles)
$actualites = $pdo->query("
    SELECT titre, contenu, image_url, date_publication, type
    FROM nouvelles 
    WHERE statut = 'publie' 
    ORDER BY date_publication DESC 
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC) ?? [];

// Événements à venir
$evenements = $pdo->query("
    SELECT titre, description, type, date_debut, lieu, image_url
    FROM evenements
    WHERE statut = 'confirme' AND date_debut >= CURDATE()
    ORDER BY date_debut
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC) ?? [];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bibliotech – Votre bibliothèque en ligne</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
             --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --primary-dark: #152a45;
            --accent: #e8b86d;
            --accent-hover: #d4a55c;
            --secondary: #3d7a8c;
            --success: #10b981;
            --success-light: #d1fae5;
            --error: #ef4444;
            --error-light: #fee2e2;
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
            --radius: 16px;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--gray-100);
            color: var(--dark);
            line-height: 1.6;
        }

        /* Hero Section - Style moderne plat */
        .hero-section {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
            min-height: 90vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            padding: 100px 0;
        }

        .hero-bg-pattern {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(255,255,255,0.05) 0%, transparent 50%);
            pointer-events: none;
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .hero-content {
            color: white;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 24px;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 24px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .hero-title span {
            color: #fcd34d;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            opacity: 0.95;
            margin-bottom: 40px;
            line-height: 1.7;
            max-width: 500px;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 50px;
        }

        .btn-primary {
            background: white;
            color: var(--primary-dark);
            padding: 16px 32px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2);
        }

        .btn-secondary {
            background: transparent;
            color: white;
            padding: 16px 32px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: 2px solid rgba(255,255,255,0.5);
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.1);
            border-color: white;
        }

        /* Stats Cards */
        .hero-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .stat-card {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.2);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 12px;
            opacity: 0.9;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            display: block;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
            font-weight: 500;
        }

        /* Hero Image/Illustration */
        .hero-visual {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .hero-image-container {
            position: relative;
            width: 100%;
            max-width: 500px;
        }

        .hero-main-image {
            width: 100%;
            height: auto;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            transform: perspective(1000px) rotateY(-5deg) rotateX(5deg);
            transition: transform 0.5s ease;
        }

        .hero-main-image:hover {
            transform: perspective(1000px) rotateY(0deg) rotateX(0deg);
        }

        .floating-card {
            position: absolute;
            background: white;
            border-radius: 16px;
            padding: 16px 20px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: float 3s ease-in-out infinite;
        }

        .floating-card.card-1 {
            top: -20px;
            right: -20px;
            animation-delay: 0s;
        }

        .floating-card.card-2 {
            bottom: 40px;
            left: -30px;
            animation-delay: 1.5s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .floating-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .floating-icon.purple { background: #ede9fe; color: #2d5a87; }
        .floating-icon.orange { background: #ffedd5; color: #ea580c; }

        /* Features Section */
        .features-section {
            padding: 100px 0;
            background: white;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-tag {
            display: inline-block;
            background: #ede9fe;
            color: #2d5a87;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 16px;
        }

        .section-subtitle {
            color: var(--gray-500);
            font-size: 1.125rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background: white;
            border: 2px solid var(--gray-100);
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            border-color: transparent;
            box-shadow: var(--shadow-xl);
            transform: translateY(-5px);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-icon-wrapper {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 2rem;
            position: relative;
        }

        .feature-icon-wrapper.security { background: #dbeafe; color: #2563eb; }
        .feature-icon-wrapper.support { background: #dcfce7; color: #16a34a; }
        .feature-icon-wrapper.delivery { background: #fef3c7; color: #d97706; }
        .feature-icon-wrapper.globe { background: #fce7f3; color: #db2777; }

        .feature-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--dark);
        }

        .feature-desc {
            color: var(--gray-500);
            line-height: 1.7;
        }

        /* Books Section */
        .books-section {
            padding: 100px 0;
            background: var(--gray-100);
        }

        .books-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 50px;
        }

        .view-all {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: gap 0.3s ease;
        }

        .view-all:hover {
            gap: 12px;
        }

        .books-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 2rem;
    }

    /* ===== CARTE DE LIVRE - CORRECTIONS ===== */
    .book-card {
        background: white;
        border-radius: var(--radius-md);
        overflow: hidden;
        box-shadow: var(--shadow);
        transition: all var(--transition-slow);
        display: flex;
        flex-direction: column;
        border: 1px solid var(--gray-100);
        position: relative;
    }

    .book-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-xl);
        border-color: var(--accent);
    }

    /* Badge Nouveau - correction classe */
    .badge-nouveau {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: var(--radius-full);
        font-size: 0.8rem;
        font-weight: 700;
        box-shadow: var(--shadow-md);
        z-index: 2;
    }

    /* Couverture - correction classe */
    .book-cover {
        height: 260px;
        background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .book-cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform var(--transition-slow);
    }

    .book-card:hover .book-cover img {
        transform: scale(1.05);
    }

    /* Fallback icône */
    .book-cover .fa-book {
        opacity: 0.5;
        transition: opacity var(--transition);
    }

    .book-card:hover .book-cover .fa-book {
        opacity: 0.7;
    }

    /* Informations - correction classe */
    .book-info {
        padding: 1.75rem;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    /* Titre - correction classe */
    .book-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.35rem;
        font-weight: 600;
        margin: 0 0 0.5rem;
        line-height: 1.3;
        color: var(--dark);
    }

    /* Auteur - correction classe */
    .book-author {
        color: var(--primary);
        font-size: 0.95rem;
        margin-bottom: 1rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .book-author::before {
        content: '\f007';
        font-family: 'Font Awesome 6 Free';
        font-weight: 400;
        font-size: 0.8rem;
    }

    /* Prix - nouvelle classe */
    .book-price {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 0.75rem;
    }

    /* Rating - correction classe */
    .book-rating {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .rating-stars {
        display: flex;
        gap: 0.15rem;
    }

    .rating-stars i {
        color: var(--accent);
        font-size: 0.85rem;
    }

    .rating-stars i.empty {
        color: var(--gray-300);
    }

    .rating-value {
        font-size: 0.85rem;
        color: var(--gray-500);
        font-weight: 500;
    }

    /* Actions - correction classe */
    .book-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: auto;
        padding-top: 1rem;
        border-top: 1px solid var(--gray-100);
    }

    /* Boutons - correction classes */
    .btn-detail {
        flex: 1;
        padding: 0.9rem;
        border: none;
        border-radius: var(--radius);
        font-weight: 600;
        cursor: pointer;
        transition: all var(--transition);
        text-align: center;
        text-decoration: none;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        background: var(--gray-100);
        color: var(--gray-700);
    }

    .btn-detail:hover {
        background: var(--primary);
        color: white;
    }

    .btn-add {
        flex: 1;
        padding: 0.9rem;
        border: none;
        border-radius: var(--radius);
        font-weight: 600;
        cursor: pointer;
        transition: all var(--transition);
        text-align: center;
        text-decoration: none;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
        color: white;
        box-shadow: var(--shadow);
    }

    .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

        /* News Section */
        .news-section {
            padding: 100px 0;
            background: white;
        }

        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }

        .news-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            border: 1px solid var(--gray-100);
        }

        .news-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .news-image {
            height: 220px;
            overflow: hidden;
            position: relative;
        }

        .news-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .news-card:hover .news-image img {
            transform: scale(1.1);
        }

        .news-date-badge {
            position: absolute;
            top: 16px;
            left: 16px;
            background: white;
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 700;
            color: var(--primary);
            box-shadow: var(--shadow-md);
        }

        .news-content {
            padding: 28px;
        }

        .news-tag {
            display: inline-block;
            background: #ede9fe;
            color: #2d5a87;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .news-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--dark);
            line-height: 1.4;
        }

        .news-excerpt {
            color: var(--gray-500);
            line-height: 1.7;
            margin-bottom: 20px;
        }

        .read-more {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: gap 0.3s ease;
        }

        .read-more:hover {
            gap: 12px;
        }

        /* Newsletter Section */
        .newsletter-section {
            padding: 100px 0;
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
            position: relative;
            overflow: hidden;
        }

        .newsletter-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .newsletter-content {
            max-width: 700px;
            margin: 0 auto;
            text-align: center;
            position: relative;
            z-index: 1;
            color: white;
        }

        .newsletter-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 2rem;
        }

        .newsletter-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .newsletter-desc {
            font-size: 1.125rem;
            opacity: 0.9;
            margin-bottom: 40px;
        }

        .newsletter-form {
            display: flex;
            gap: 12px;
            max-width: 500px;
            margin: 0 auto;
            background: rgba(255,255,255,0.2);
            padding: 8px;
            border-radius: 16px;
            backdrop-filter: blur(10px);
        }

        .newsletter-input {
            flex: 1;
            padding: 16px 20px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            background: white;
            outline: none;
        }

        .newsletter-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .newsletter-btn:hover {
            background: #db2777;
            transform: scale(1.05);
        }

        .newsletter-privacy {
            margin-top: 24px;
            font-size: 0.875rem;
            opacity: 0.8;
        }

        .newsletter-privacy a {
            color: white;
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .hero-container {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .hero-content {
                order: 2;
            }

            .hero-visual {
                order: 1;
            }

            .hero-title {
                font-size: 2.5rem;
            }

            .hero-subtitle {
                margin: 0 auto 40px;
            }

            .hero-stats {
                max-width: 400px;
                margin: 0 auto;
            }

            .hero-buttons {
                justify-content: center;
            }

            .floating-card {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }

            .section-title {
                font-size: 1.875rem;
            }

            .books-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }

            .newsletter-form {
                flex-direction: column;
                background: transparent;
                padding: 0;
            }

            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 480px) {
            .hero-stats {
                grid-template-columns: 1fr;
            }

            .features-grid,
            .news-grid {
                grid-template-columns: 1fr;
            }

            .book-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-bg-pattern"></div>
    <div class="hero-container">
        <div class="hero-content">
            <div class="hero-badge">
                <i class="fas fa-sparkles"></i>
                Nouveau : Emprunt en ligne disponible
            </div>
            <h1 class="hero-title">Découvrez, Empruntez, <span>Achetez</span></h1>
            <p class="hero-subtitle">Accédez à des milliers de livres depuis chez vous. Bibliotech révolutionne votre façon de lire avec notre bibliothèque numérique moderne.</p>
            
            <div class="hero-buttons">
                <a href="catalogue.php" class="btn-primary">
                    <i class="fas fa-compass"></i> Explorer le catalogue
                </a>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="register.php" class="btn-secondary">
                        <i class="fas fa-user-plus"></i> Créer un compte
                    </a>
                <?php endif; ?>
            </div>

            <div class="hero-stats">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                    <span class="stat-number"><?= number_format($stats['livres']) ?>+</span>
                    <span class="stat-label">Livres disponibles</span>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-copy"></i></div>
                    <span class="stat-number"><?= number_format($stats['disponibles']) ?></span>
                    <span class="stat-label">Exemplaires</span>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <span class="stat-number"><?= number_format($stats['membres']) ?>+</span>
                    <span class="stat-label">Membres actifs</span>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book-reader"></i></div>
                    <span class="stat-number"><?= number_format($stats['emprunts']) ?></span>
                    <span class="stat-label">Emprunts en cours</span>
                </div>
            </div>
        </div>

        <div class="hero-visual">
            <div class="hero-image-container">
                <img src="./41289.jpg" 
                     alt="Bibliothèque en ligne" 
                     class="hero-main-image">
                
                <div class="floating-card card-1">
                    <div class="floating-icon purple">
                        <i class="fas fa-star"></i>
                    </div>
                    <div>
                        <div style="font-weight: 700; color: var(--dark);">4.9/5</div>
                        <div style="font-size: 0.75rem; color: var(--gray-500);">Note moyenne</div>
                    </div>
                </div>

                <div class="floating-card card-2">
                    <div class="floating-icon orange">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div>
                        <div style="font-weight: 700; color: var(--dark);">+500</div>
                        <div style="font-size: 0.75rem; color: var(--gray-500);">Nouveautés ce mois</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <div class="container">
        <div class="section-header">
            <span class="section-tag">Nos avantages</span>
            <h2 class="section-title">Pourquoi choisir Bibliotech ?</h2>
            <p class="section-subtitle">Une expérience de lecture moderne et accessible à tous, conçue pour les amoureux des livres.</p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon-wrapper security">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3 class="feature-title">Paiement sécurisé</h3>
                <p class="feature-desc">Transactions 100% sécurisées avec cryptage SSL. Vos données sont protégées à chaque achat.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-wrapper support">
                    <i class="fas fa-headset"></i>
                </div>
                <h3 class="feature-title">Conseil personnalisé</h3>
                <p class="feature-desc">Nos bibliothécaires vous accompagnent dans vos choix de lecture et vos recherches.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-wrapper delivery">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <h3 class="feature-title">Livraison rapide</h3>
                <p class="feature-desc">Recevez vos livres en 24-48h partout au Maroc. Emprunt et achat disponibles.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon-wrapper globe">
                    <i class="fas fa-globe"></i>
                </div>
                <h3 class="feature-title">Catalogue francophone</h3>
                <p class="feature-desc">La plus grande sélection de livres en français en Afrique du Nord.</p>
            </div>
        </div>
    </div>
</section>

<!-- Livres populaires -->
<section class="books-section">
    <div class="container">
        <div class="books-header">
            <div>
                <span class="section-tag">Collection</span>
                <h2 class="section-title" style="margin: 0;">Livres populaires</h2>
            </div>
            <a href="catalogue.php" class="view-all">
                Voir tout le catalogue <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div class="books-grid" id="grid">
            <?php if (empty($livres_populaires)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>Aucun livre populaire pour le moment</h3>
                    <p>Revenez bientôt !</p>
                </div>
            <?php else: ?>
                <?php foreach ($livres_populaires as $livre): ?>
                    <div class="book-card"
                         data-title="<?= htmlspecialchars(strtolower($livre['titre'])) ?>"
                         data-author="<?= htmlspecialchars(strtolower($livre['auteur'] ?? '')) ?>">

                        <?php if (strtotime($livre['created_at'] ?? 'now') > strtotime('-30 days')): ?>
                            <div class="badge-nouveau">NOUVEAU</div>
                        <?php endif; ?>

                        <div class="book-cover">
                            <?php if (!empty($livre['couverture_url'])): ?>
                                <img src="<?= htmlspecialchars($livre['couverture_url']) ?>" 
                                     alt="Couverture : <?= htmlspecialchars($livre['titre']) ?>">
                            <?php else: ?>
                                <div style="height:100%;display:flex;align-items:center;justify-content:center;color:#94a3b8;">
                                    <i class="fas fa-book fa-5x"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="book-info">
                            <h3 class="book-title"><?= htmlspecialchars($livre['titre']) ?></h3>
                            <div class="book-author">
                                <?= htmlspecialchars($livre['auteur'] ?? 'Auteur inconnu') ?>
                            </div>

                            <div class="book-price">
                                <?= number_format($livre['prix'] ?? 0, 2) ?> DH
                            </div>

                            <div class="book-rating">
                                <div class="rating-stars">
                                    <?php
                                    $note = $livre['note_moyenne'] ?? 0;
                                    for ($i = 1; $i <= 5; $i++):
                                        if ($i <= floor($note)):
                                            echo '<i class="fas fa-star"></i>';
                                        elseif ($i - 0.5 <= $note):
                                            echo '<i class="fas fa-star-half-alt"></i>';
                                        else:
                                            echo '<i class="far fa-star"></i>';
                                        endif;
                                    endfor;
                                    ?>
                                </div>
                                <span class="rating-value"><?= number_format($note, 1) ?></span>
                            </div>

                            <div class="book-actions">
                                <a href="details_livre.php?id=<?= $livre['id'] ?>" class="btn-detail">
                                    Détails
                                </a>
                                <?php if ($livre['dispo'] > 0): ?>
                                    <form action="panier.php" method="post" style="flex:1">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="livre_id" value="<?= $livre['id'] ?>">
                                        <button type="submit" class="btn-add">
                                            Ajouter au panier
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn-add" disabled>Indisponible</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Actualités -->
<?php if (!empty($actualites)): ?>
    <section class="news-section">
        <div class="container">
            <div class="section-header">
                <span class="section-tag">Actualités</span>
                <h2 class="section-title">Dernières nouvelles & Événements</h2>
                <p class="section-subtitle">Restez informé des dernières actualités de notre bibliothèque et des événements à venir.</p>
            </div>

            <div class="news-grid">
                <?php foreach ($actualites as $news): ?>
                    <div class="news-card">
                        <?php if (!empty($news['image_url'])): ?>
                            <div class="news-image">
                                <img src="<?= htmlspecialchars($news['image_url']) ?>" alt="<?= htmlspecialchars($news['titre']) ?>">
                                <div class="news-date-badge">
                                    <?php 
                                        if (!empty($news['date_publication'])) {
                                            $date = new DateTime($news['date_publication']);
                                            echo $date->format('d M');
                                        } else {
                                            echo date('d M');
                                        }
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="news-content">
                            <span class="news-tag"><?= htmlspecialchars($news['type'] ?? 'Actualité') ?></span>
                            <h3 class="news-title"><?= htmlspecialchars($news['titre'] ?? 'Sans titre') ?></h3>
                            <p class="news-excerpt"><?= htmlspecialchars(substr($news['contenu'] ?? '', 0, 120)) ?>…</p>
                            <a href="actualites.php" class="read-more">
                                Lire l'article <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- Newsletter -->
<section class="newsletter-section">
    <div class="newsletter-content">
        <div class="newsletter-icon">
            <i class="fas fa-paper-plane"></i>
        </div>
        <h2 class="newsletter-title">Rejoignez notre communauté</h2>
        <p class="newsletter-desc">Recevez nos offres exclusives, les nouveautés littéraires et nos événements en avant-première directement dans votre boîte mail.</p>
        
        <form action="newsletter.php" method="POST" class="newsletter-form">
            <input type="email" name="email" placeholder="Votre adresse email" required class="newsletter-input">
            <button type="submit" class="newsletter-btn">
                S'inscrire <i class="fas fa-arrow-right"></i>
            </button>
        </form>
        
        <p class="newsletter-privacy">
            En vous inscrivant, vous acceptez notre <a href="confidentialite.php">Politique de confidentialité</a>. Désinscription possible à tout moment.
        </p>
    </div>
</section>

<?php require_once 'footer.php'; ?>

</body>
</html>