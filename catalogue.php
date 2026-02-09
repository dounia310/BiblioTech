<?php
// 1. Session + en-têtes anti-cache (très important pour CSRF)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once 'connexion.php';
require_once 'header.php'; 

// 1.Pagination
$limit  = 20;                          
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// 2.Calcul du nombre total pour les liens de pagination
$total_stmt = $pdo->query("SELECT COUNT(*) FROM livres");
$total_books = $total_stmt->fetchColumn();
$total_pages = ceil($total_books / $limit);



// 3. Récupération des données
try {
    $stmt = $pdo->prepare("
    SELECT
        l.id,
        l.titre,
        a.nom_complet AS auteur,
        l.couverture_url,
        l.prix,
        COALESCE(l.note_moyenne, 0) AS note_moyenne,
        l.created_at,
        GROUP_CONCAT(DISTINCT c.nom ORDER BY c.nom SEPARATOR ', ') AS categories,
        SUM(CASE WHEN ex.statut = 'disponible' THEN 1 ELSE 0 END) AS exemplaires_disponibles
    FROM livres l
    LEFT JOIN auteurs a ON l.auteur_id = a.id
    LEFT JOIN livre_categories lc ON l.id = lc.livre_id
    LEFT JOIN categories c ON lc.categorie_id = c.id
    LEFT JOIN exemplaires ex ON l.id = ex.livre_id
    GROUP BY l.id
    ORDER BY l.created_at DESC, l.titre ASC
    LIMIT :limit OFFSET :offset
");

$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$livres = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

    $stats = [
        'livres'      => (int) $pdo->query("SELECT COUNT(*) FROM livres")->fetchColumn(),
        'disponibles' => (int) $pdo->query("SELECT COUNT(*) FROM exemplaires WHERE statut = 'disponible'")->fetchColumn(),
        'empruntes'   => (int) $pdo->query("SELECT COUNT(*) FROM exemplaires WHERE statut != 'disponible'")->fetchColumn(),
        'auteurs'     => (int) $pdo->query("SELECT COUNT(DISTINCT auteur_id) FROM livres WHERE auteur_id IS NOT NULL")->fetchColumn(),
    ];

    $categories = $pdo->query("SELECT nom FROM categories ORDER BY nom")
                      ->fetchAll(PDO::FETCH_COLUMN) ?? [];

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Catalogue PDO Error: " . $e->getMessage());
    die("Erreur temporaire de la base de données. Réessayez dans quelques instants.");
}





// 4. CSRF
if (!isset($_SESSION['csrf_reservation'])) {
    $_SESSION['csrf_reservation'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_reservation'];

if (isset($_GET['debug_csrf'])) {
    echo "CSRF token actuel (session) : " . htmlspecialchars($csrf_token) . "\n";
    echo "Session ID : " . session_id() . "\n";
    echo "</pre>";
    $_SESSION['csrf_last_gen'] = date('Y-m-d H:i:s');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Catalogue – Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Palette Bibliotech - harmonisée avec header/footer */
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
            
            /* Ombres et transitions */
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            
            --radius-sm: 6px;
            --radius: 12px;
            --radius-md: 16px;
            --radius-lg: 24px;
            --radius-full: 9999px;
            
            --transition-fast: 150ms ease;
            --transition: 250ms ease;
            --transition-slow: 350ms ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-50);
            color: var(--dark);
            line-height: 1.6;
        }

        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
            padding: 0 24px; 
        }

        /* ===== HERO SECTION ===== */
        .hero {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 50%, var(--primary-light) 100%);
            color: white;
            padding: 5rem 2rem 6rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            opacity: 0.5;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero h1 { 
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem; 
            margin: 0 0 1rem; 
            font-weight: 700; 
            letter-spacing: -1px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .hero p { 
            font-size: 1.25rem; 
            opacity: 0.9; 
            max-width: 600px; 
            margin: 0 auto 2rem; 
            font-weight: 300;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(232, 184, 109, 0.2);
            border: 1px solid rgba(232, 184, 109, 0.3);
            padding: 8px 20px;
            border-radius: var(--radius-full);
            font-size: 0.9rem;
            color: var(--accent);
        }

        .hero-badge i {
            color: var(--accent);
        }

        /* ===== STATS ===== */
        .stats-section {
            margin-top: -3rem;
            position: relative;
            z-index: 10;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius-md);
            padding: 2rem 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-100);
            transition: transform var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
        }

        .stat-icon.accent {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
            color: var(--primary-dark);
        }

        .stat-number { 
            font-size: 2.5rem; 
            font-weight: 800; 
            color: var(--primary);
            line-height: 1;
        }

        .stat-label { 
            font-size: 0.9rem; 
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 0.5rem;
            font-weight: 500;
        }

        /* ===== CONTROLS / FILTRES ===== */
        .controls-section {
            margin: 3rem 0 2rem;
        }

        .controls {
            background: white;
            border-radius: var(--radius-md);
            padding: 1.5rem 2rem;
            box-shadow: var(--shadow-md);
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            border: 1px solid var(--gray-200);
        }

        .search-box {
            flex: 1 1 350px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3.5rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-full);
            font-size: 1rem;
            transition: all var(--transition);
            background: var(--gray-50);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--accent);
            background: white;
            box-shadow: 0 0 0 4px rgba(232, 184, 109, 0.15);
        }

        .search-icon {
            position: absolute;
            left: 1.3rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 1.1rem;
        }

        .filter-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .select-wrapper {
            position: relative;
        }

        .select {
            padding: 1rem 3rem 1rem 1.5rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-full);
            background: white;
            font-size: 0.95rem;
            min-width: 180px;
            cursor: pointer;
            appearance: none;
            transition: all var(--transition);
            color: var(--gray-700);
        }

        .select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(232, 184, 109, 0.15);
        }

        .select-wrapper::after {
            content: '\f107';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            pointer-events: none;
        }

        /* ===== BOOKS GRID ===== */
       
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
     
    /* Pagination */
    .pagination a:hover {
    opacity: 0.85;
    transform: scale(1.05);
}
    /* ===== RESPONSIVE ===== */
    @media (max-width: 992px) {
        .books-grid {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .books-grid {
            grid-template-columns: 1fr;
        }
        
        .book-cover {
            height: 220px;
        }
        
        .book-info {
            padding: 1.5rem;
        }
        
        .book-title {
            font-size: 1.2rem;
        }
    }

    @media (max-width: 480px) {
        .book-actions {
            flex-direction: column;
        }
    }
</style>
</head>
<body>

<!-- Hero Section -->
<header class="hero">
    <div class="hero-content">
        <div class="hero-badge">
            <i class="fas fa-book-open"></i>
            Explorez notre collection
        </div>
        <h1>Notre Catalogue</h1>
        <p>Découvrez plus de <?= number_format($stats['livres']) ?> ouvrages soigneusement sélectionnés</p>
    </div>
</header>

<!-- Stats Section -->
<section class="stats-section">
    <div class="container">
        <div class="stats">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-book"></i></div>
                <div class="stat-number"><?= number_format($stats['livres']) ?></div>
                <div class="stat-label">Livres</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon accent"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?= number_format($stats['disponibles']) ?></div>
                <div class="stat-label">Disponibles</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-bookmark"></i></div>
                <div class="stat-number"><?= number_format($stats['empruntes']) ?></div>
                <div class="stat-label">Empruntés</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon accent"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?= number_format($stats['auteurs']) ?></div>
                <div class="stat-label">Auteurs</div>
            </div>
        </div>
    </div>
</section>

<!-- Controls / Filtres -->
<section class="controls-section">
    <div class="container">
        <div class="controls">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="search" class="search-input" placeholder="Rechercher par titre, auteur...">
            </div>
            <div class="filter-group">
                <div class="select-wrapper">
                    <select id="category" class="select">
                        <option value="">Toutes les catégories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars(strtolower($cat)) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="select-wrapper">
                <select id="sort" class="select">
                    <option value="pop">Popularité</option>
                    <option value="title">Titre (A-Z)</option>
                    <option value="year">Année (récent)</option>
                    <option value="price_asc">Prix croissant</option>
                    <option value="price_desc">Prix décroissant</option>
                </select>
</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Books Grid -->
<main class="books-section">
    <div class="container">
        <div class="books-grid" id="grid">
            <?php if (empty($livres)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>Aucun livre trouvé</h3>
                    <p>Revenez bientôt ou modifiez vos filtres.</p>
                </div>
            <?php else: ?>
                <?php foreach ($livres as $l): ?>
                    <div class="book-card"
                         data-title="<?= htmlspecialchars(strtolower($l['titre'])) ?>"
                         data-author="<?= htmlspecialchars(strtolower($l['auteur'] ?? '')) ?>"
                         data-cat="<?= htmlspecialchars(strtolower($l['categories'] ?? '')) ?>"
                         data-price="<?= (float)($l['prix'] ?? 0) ?>"
                         data-date="<?= $l['created_at'] ?>">

                        <?php if (strtotime($l['created_at']) > strtotime('-30 days')): ?>
                            <div class="badge-nouveau">NOUVEAU</div>
                        <?php endif; ?>

                        <div class="book-cover">
                            <?php if (!empty($l['couverture_url'])): ?>
                                <img src="<?= htmlspecialchars($l['couverture_url']) ?>" 
                                     alt="Couverture <?= htmlspecialchars($l['titre']) ?>">
                            <?php else: ?>
                                <div style="height:100%;display:flex;align-items:center;justify-content:center;color:#94a3b8;">
                                    <i class="fas fa-book fa-5x"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="book-info">
                            <h3 class="book-title"><?= htmlspecialchars($l['titre']) ?></h3>
                            <div class="book-author"><?= htmlspecialchars($l['auteur'] ?? 'Auteur inconnu') ?></div>
                            
                            <div class="book-price">
                                <?= number_format($l['prix'] ?? 0, 2) ?> DH
                            </div>

                            <div class="book-rating">
                                <div class="rating-stars">
                                    <?php
                                    $note = $l['note_moyenne'] ?? 0;
                                    for ($i = 1; $i <= 5; $i++):
                                        if ($i <= floor($note)) echo '<i class="fas fa-star"></i>';
                                        elseif ($i - 0.5 <= $note) echo '<i class="fas fa-star-half-alt"></i>';
                                        else echo '<i class="far fa-star"></i>';
                                    endfor;
                                    ?>
                                </div>
                                <span class="rating-value"><?= number_format($note, 1) ?></span>
                            </div>

                            <div class="book-actions">
                                <a href="details_livre.php?id=<?= $l['id'] ?>" class="btn-detail">Détails</a>
                                <form action="panier.php" method="post" style="flex:1">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="livre_id" value="<?= $l['id'] ?>">
                                    <button type="submit" class="btn-add">Ajouter au panier</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <div class="pagination" style="text-align: center; margin: 3rem 0; font-size: 1.1rem;">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>" style="margin: 0 10px; color: var(--primary); text-decoration: none; font-weight: bold;">« Précédent</a>
        <?php endif; ?>

        <?php
        // Afficher quelques numéros de page (ex: 1 ... 4 5 6 ... 12)
        $range = 2; // pages à montrer de chaque côté de la page courante
        for ($i = max(1, $page - $range); $i <= min($total_pages, $page + $range); $i++):
        ?>
            <a href="?page=<?= $i ?>" 
               style="margin: 0 8px; padding: 8px 14px; border-radius: 8px; 
                      background: <?= $i === $page ? 'var(--primary)' : '#e2e8f0' ?>; 
                      color: <?= $i === $page ? 'white' : 'var(--dark)' ?>; 
                      text-decoration: none; font-weight: <?= $i === $page ? 'bold' : 'normal' ?>;">
                <?= $i ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>" style="margin: 0 10px; color: var(--primary); text-decoration: none; font-weight: bold;">Suivant »</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
function filterBooks() {
    const query = document.getElementById('search').value.toLowerCase().trim();
    const category = document.getElementById('category').value.toLowerCase();
    const sortValue = document.getElementById('sort').value;

    let cards = Array.from(document.querySelectorAll('.book-card'));

    // Filtrage  
    cards = cards.filter(card => {
        const title  = card.dataset.title;
        const author = card.dataset.author;
        const cats   = card.dataset.cat;

        return (!query || title.includes(query) || author.includes(query)) &&
               (!category || cats.includes(category));
    });

    // Tri selon la valeur sélectionnée
    if (sortValue === 'title') {
        cards.sort((a, b) => a.dataset.title.localeCompare(b.dataset.title));
    }
    else if (sortValue === 'year') {
        cards.sort((a, b) => Number(b.dataset.year) - Number(a.dataset.year));
    }
    else if (sortValue === 'price_asc') {
        cards.sort((a, b) => Number(a.dataset.price) - Number(b.dataset.price));
    }
    else if (sortValue === 'price_desc') {
        cards.sort((a, b) => Number(b.dataset.price) - Number(a.dataset.price));
    }
    else {
        // Par défaut : popularité (ou created_at si tu préfères)
        cards.sort((a, b) => Number(b.dataset.pop) - Number(a.dataset.pop));
    }

    const grid = document.getElementById('grid');
    if (cards.length === 0) {
        grid.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>Aucun résultat</h3>
                <p>Essayez avec d'autres critères de recherche.</p>
            </div>
        `;
    } else {
        grid.replaceChildren(...cards);
    }
}

document.getElementById('search').addEventListener('input', filterBooks);
document.getElementById('category').addEventListener('change', filterBooks);
document.getElementById('sort').addEventListener('change', filterBooks);
</script>

</body>
</html>
<?php require_once 'footer.php'; ?>