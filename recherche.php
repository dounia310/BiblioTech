<?php
// recherche.php – VERSION AVEC CATALOGUE INTÉGRÉ
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'connexion.php';

$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

$search = [
    'q'        => trim($_GET['q'] ?? $_POST['q'] ?? ''),
    'categorie'=> $_GET['categorie'] ?? $_POST['categorie'] ?? 'tous',
    'auteur'   => trim($_GET['auteur'] ?? $_POST['auteur'] ?? ''),
    'disponible'=> $_GET['disponible'] ?? $_POST['disponible'] ?? '',
    'page'     => min(max(1, (int)($_GET['page'] ?? 1)), 1000),
    'tri'      => $_GET['tri'] ?? 'titre_asc', // pour le catalogue
];

$limit  = 12;
$offset = ($search['page'] - 1) * $limit;

// ────────────────────────────────────────────────
// Déterminer si on est en mode "catalogue" ou "recherche"
$is_catalogue = empty($search['q']) 
             && $search['categorie'] === 'tous' 
             && empty($search['auteur']) 
             && empty($search['disponible']);

// ────────────────────────────────────────────────
// Fonction WHERE (utilisée seulement en mode recherche)
function buildWhereConditions($search, &$where_conditions, &$where_params) {
    if ($search['q'] !== '') {
        $like = "%{$search['q']}%";
        $where_conditions[] = "(l.titre LIKE ? OR a.nom LIKE ? OR a.prenom LIKE ?)";
        $where_params[] = $like; $where_params[] = $like; $where_params[] = $like;
    }
    if ($search['categorie'] !== 'tous' && is_numeric($search['categorie'])) {
        $where_conditions[] = "EXISTS (SELECT 1 FROM livre_categories lc WHERE lc.livre_id = l.id AND lc.categorie_id = ?)";
        $where_params[] = (int)$search['categorie'];
    }
    if ($search['auteur'] !== '') {
        if (is_numeric($search['auteur'])) {
            $where_conditions[] = "EXISTS (SELECT 1 FROM livre_auteurs la WHERE la.livre_id = l.id AND la.auteur_id = ?)";
            $where_params[] = (int)$search['auteur'];
        } else {
            $like = "%{$search['auteur']}%";
            $where_conditions[] = "(a.nom LIKE ? OR a.prenom LIKE ? OR CONCAT(a.prenom, ' ', a.nom) LIKE ?)";
            $where_params[] = $like; $where_params[] = $like; $where_params[] = $like;
        }
    }
}

// ────────────────────────────────────────────────
// Construction SQL de base
$where_conditions = $where_params = [];
$having_clause    = "";

if (!$is_catalogue) {
    buildWhereConditions($search, $where_conditions, $where_params);
}

$where_clause = empty($where_conditions) ? "WHERE 1=1" : "WHERE " . implode(" AND ", $where_conditions);

// ────────────────────────────────────────────────
// Tri différent selon le mode
$order_by = "ORDER BY l.titre ASC"; // défaut

if ($is_catalogue) {
    // Options de tri pour le catalogue
    switch ($search['tri']) {
        case 'date_desc': $order_by = "ORDER BY l.annee_publication DESC, l.titre ASC"; break;
        case 'dispo_desc':$order_by = "ORDER BY dispo DESC, l.titre ASC"; break;
        case 'titre_desc':$order_by = "ORDER BY l.titre DESC"; break;
        default:          $order_by = "ORDER BY l.titre ASC";
    }
}

// ────────────────────────────────────────────────
// Requête principale (livres)
$sql = "
    SELECT l.id, l.titre, l.annee_publication, l.couverture_url,
           COUNT(DISTINCT e.id) AS total_exemplaires,
           SUM(CASE WHEN e.statut = 'disponible' THEN 1 ELSE 0 END) AS dispo,
           GROUP_CONCAT(DISTINCT CONCAT(a.prenom, ' ', a.nom) SEPARATOR ', ') AS auteurs
    FROM livres l
    LEFT JOIN exemplaires e ON l.id = e.livre_id
    LEFT JOIN livre_auteurs la ON l.id = la.livre_id
    LEFT JOIN auteurs a ON la.auteur_id = a.id
    $where_clause
    GROUP BY l.id
";

if ($search['disponible'] === 'oui') {
    $sql .= " HAVING dispo > 0";
} elseif ($search['disponible'] === 'non') {
    $sql .= " HAVING dispo = 0";
}

$sql .= " $order_by LIMIT ? OFFSET ?";
$params = $where_params;
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
foreach ($params as $i => $val) {
    $stmt->bindValue($i+1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$livres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ────────────────────────────────────────────────
// Comptage total
if ($is_catalogue) {
    // Simple et rapide pour le catalogue
    $count_sql = "SELECT COUNT(*) FROM livres";
    $total = (int) $pdo->query($count_sql)->fetchColumn();
} else {
    // Même logique que dans votre version précédente (avec HAVING si filtre dispo)
    if (empty($search['disponible']) || !in_array($search['disponible'], ['oui','non'])) {
        $count_sql = "SELECT COUNT(DISTINCT l.id) FROM livres l $where_clause";
        $count_params = $where_params;
    } else {
        $having = $search['disponible'] === 'oui' ? "> 0" : "= 0";
        $count_sql = "
            SELECT COUNT(*) FROM (
                SELECT l.id
                FROM livres l
                LEFT JOIN exemplaires e ON l.id = e.livre_id
                $where_clause
                GROUP BY l.id
                HAVING SUM(CASE WHEN e.statut = 'disponible' THEN 1 ELSE 0 END) $having
            ) sub
        ";
        $count_params = $where_params;
    }

    $stmt_count = $pdo->prepare($count_sql);
    foreach ($count_params as $i => $val) {
        $stmt_count->bindValue($i+1, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt_count->execute();
    $total = (int) $stmt_count->fetchColumn();
}

$total_pages = max(1, ceil($total / $limit));

// ────────────────────────────────────────────────
// Listes déroulantes
$categories_list = $pdo->query("SELECT id, nom FROM categories ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);
$auteurs_list    = $pdo->query("SELECT id, CONCAT(COALESCE(prenom,''), ' ', nom) AS nom_complet FROM auteurs WHERE nom != '' ORDER BY nom, prenom")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_catalogue ? 'Catalogue' : 'Recherche' ?> – Bibliotech</title>
    <!-- vos liens css + fonts identiques -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
    :root{--primary:#1e6a72;--primary-dark:#145459;--accent:#fd7e14;--glass-bg:rgba(255,255,255,0.12);--glass-border:rgba(255,255,255,0.22);--text-light:rgba(255,255,255,0.95);--text-muted:rgba(255,255,255,0.80);--radius:20px;--shadow:0 12px 35px rgba(0,0,0,0.28);--blur:14px;}*{margin:0;padding:0;box-sizing:border-box;}body{font-family:'Roboto',system-ui,sans-serif;min-height:100vh;background:url('https://i.pinimg.com/1200x/27/b5/90/27b590f352b9d8ed79063df4bd6ddf75.jpg') center/cover no-repeat fixed;color:white;position:relative;padding:2rem 1rem;}body::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(30,106,114,0.50),rgba(108,117,125,0.40));backdrop-filter:blur(3px);z-index:-1;}header{text-align:center;margin-bottom:2.5rem;text-shadow:0 3px 12px rgba(0,0,0,0.5);}header h1{font-size:2.6rem;font-weight:700;margin-bottom:0.5rem;}header p{font-size:1.15rem;opacity:0.9;}.container{max-width:1280px;margin:0 auto;}.search-form{background:var(--glass-bg);backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));border:1px solid var(--glass-border);border-radius:var(--radius);padding:2.2rem 2rem;box-shadow:var(--shadow);margin-bottom:2.5rem;transition:transform 0.35s ease,box-shadow 0.35s ease;}.search-form:hover{transform:translateY(-8px);box-shadow:0 20px 50px rgba(0,0,0,0.35);}.form-row{display:flex;gap:1.2rem;flex-wrap:wrap;margin-bottom:1.4rem;}.form-row input,.form-row select{flex:1;min-width:220px;padding:0.95rem 1.3rem;background:rgba(255,255,255,0.18);border:1px solid rgba(255,255,255,0.25);border-radius:14px;color:white;font-size:1rem;transition:all 0.25s ease;}.form-row input::placeholder,.form-row select option{color:rgba(255,255,255,0.65);}.form-row input:focus,.form-row select:focus{background:rgba(255,255,255,0.25);border-color:var(--primary);box-shadow:0 0 0 4px rgba(30,106,114,0.35);outline:none;}.btn{padding:0.95rem 1.9rem;background:linear-gradient(90deg,var(--primary),#4da8af);border:none;border-radius:14px;color:white;font-weight:600;font-size:1.05rem;cursor:pointer;transition:all 0.3s ease;box-shadow:0 6px 20px rgba(30,106,114,0.45);display:inline-flex;align-items:center;gap:0.6rem;}.btn:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(30,106,114,0.6);}.btn-reset{background:linear-gradient(90deg,#e74c3c,#c0392b);}.filters-summary{background:rgba(255,255,255,0.15);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.2);border-radius:16px;padding:1.3rem 1.6rem;margin:1.5rem 0;font-size:1.02rem;box-shadow:0 8px 25px rgba(0,0,0,0.2);}.filters-summary strong{color:#ffd166;}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:2rem;}.book-card{background:var(--glass-bg);backdrop-filter:blur(var(--blur));-webkit-backdrop-filter:blur(var(--blur));border:1px solid var(--glass-border);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);transition:all 0.35s ease;backdrop-filter:blur(12px);}.book-card:hover{transform:translateY(-12px);box-shadow:0 22px 55px rgba(0,0,0,0.38);}.book-cover{height:320px;position:relative;background:rgba(0,0,0,0.25);overflow:hidden;}.book-cover img{width:100%;height:100%;object-fit:cover;transition:transform 0.45s ease;}.book-card:hover .book-cover img{transform:scale(1.08);}.no-cover{height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.6);font-size:3.8rem;}.book-info{padding:1.4rem 1.6rem;}.title{font-size:1.28rem;font-weight:700;margin-bottom:0.7rem;color:var(--text-light);line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}.auteurs{color:var(--text-muted);font-size:0.98rem;margin-bottom:0.9rem;font-style:italic;}.meta{display:flex;flex-wrap:wrap;gap:1.1rem;font-size:0.96rem;color:var(--text-muted);align-items:center;}.dispo{color:#2ecc71;font-weight:600;}.indispo{color:#e74c3c;font-weight:600;}.pagination{display:flex;justify-content:center;flex-wrap:wrap;gap:0.8rem;margin:3.5rem 0;}.pagination a{padding:0.8rem 1.4rem;background:rgba(255,255,255,0.15);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.25);border-radius:14px;color:white;text-decoration:none;font-weight:600;transition:all 0.25s;}.pagination a:hover,.pagination a.active{background:rgba(30,106,114,0.45);border-color:var(--primary);transform:translateY(-3px);}.empty,.error{background:var(--glass-bg);backdrop-filter:blur(var(--blur));border:1px solid var(--glass-border);border-radius:var(--radius);padding:3.5rem 2rem;text-align:center;box-shadow:var(--shadow);margin:2rem 0;}.empty i{font-size:6rem;color:rgba(255,255,255,0.4);margin-bottom:1.5rem;}.empty h2{font-size:2rem;margin-bottom:1rem;}@media (max-width:992px){.form-row{flex-direction:column;}.grid{gap:1.6rem;}}@media (max-width:640px){.search-form{padding:1.8rem 1.4rem;}header h1{font-size:2.2rem;}}</style>
    </style>
</head>
<body>

<header>
    <h1><i class="fas <?= $is_catalogue ? 'fa-book-open' : 'fa-search' ?>"></i> 
        Bibliotech – <?= $is_catalogue ? 'Catalogue' : 'Recherche avancée' ?>
    </h1>
    <p><?= number_format($total) ?> référence<?= $total > 1 ? 's' : '' ?> disponibles</p>
</header>

<div class="container">

    <!-- Formulaire (toujours présent) -->
    <form method="get" class="search-form">
        <div class="form-row">
            <input type="text" name="q" placeholder="Titre, auteur, mot-clé..." value="<?= htmlspecialchars($search['q']) ?>">
        </div>
        <div class="form-row">
            <select name="categorie">
                <option value="tous">📚 Toutes les catégories</option>
                <?php foreach ($categories_list as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $search['categorie'] == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['nom']) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="auteur">
                <option value="">👤 Tous les auteurs</option>
                <?php foreach ($auteurs_list as $aut): ?>
                <option value="<?= $aut['id'] ?>" <?= $search['auteur'] == $aut['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($aut['nom_complet']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <select name="disponible">
                <option value="">📖 Tous (disponibilité)</option>
                <option value="oui"  <?= $search['disponible']==='oui'  ? 'selected':'' ?>>✅ Disponible</option>
                <option value="non"  <?= $search['disponible']==='non'  ? 'selected':'' ?>>❌ Indisponible</option>
            </select>

            <?php if ($is_catalogue): ?>
            <select name="tri">
                <option value="titre_asc"  <?= $search['tri']==='titre_asc'  ? 'selected':'' ?>>Titre A → Z</option>
                <option value="titre_desc" <?= $search['tri']==='titre_desc' ? 'selected':'' ?>>Titre Z → A</option>
                <option value="date_desc"  <?= $search['tri']==='date_desc'  ? 'selected':'' ?>>Plus récents d'abord</option>
                <option value="dispo_desc" <?= $search['tri']==='dispo_desc' ? 'selected':'' ?>>Les plus disponibles</option>
            </select>
            <?php endif; ?>

            <input type="hidden" name="page" value="1">
        </div>

        <div style="display:flex; gap:1rem; justify-content:flex-end; flex-wrap:wrap;">
            <button type="submit" class="btn">
                <i class="fas fa-magnifying-glass"></i> <?= $is_catalogue ? 'Filtrer' : 'Rechercher' ?> (<?= number_format($total) ?>)
            </button>
            <a href="recherche.php" class="btn btn-reset">
                <i class="fas fa-refresh"></i> Réinitialiser
            </a>
        </div>
    </form>

    <!-- Résumé filtres (uniquement si recherche active) -->
    <?php if (!$is_catalogue && ($search['q'] || $search['categorie'] !== 'tous' || $search['auteur'] || $search['disponible'])): ?>
    <div class="filters-summary">
        <strong><i class="fas fa-filter"></i> Filtres actifs :</strong>
        <?php if ($search['q']): ?> « <?= htmlspecialchars($search['q']) ?> »<?php endif; ?>
        <?php if ($search['categorie'] !== 'tous'): ?>
            Catégorie : <strong><?= htmlspecialchars(array_column($categories_list,'nom','id')[$search['categorie']] ?? '?') ?></strong><?php endif; ?>
        <?php if ($search['auteur']): ?>
            Auteur : <strong><?= htmlspecialchars(array_column($auteurs_list,'nom_complet','id')[$search['auteur']] ?? '?') ?></strong><?php endif; ?>
        <?php if ($search['disponible']): ?> <strong><?= $search['disponible']==='oui'?'Disponible':'Indisponible' ?></strong><?php endif; ?>
        • <?= number_format($total) ?> résultat<?= $total>1?'s':'' ?>
    </div>
    <?php endif; ?>

    <!-- Grille de livres (commune aux deux modes) -->
    <?php if (empty($livres)): ?>
        <div class="empty">
            <i class="fas fa-book-open"></i>
            <h2>Aucun livre trouvé</h2>
            <p>Essayez d'élargir vos critères.</p>
            <a href="recherche.php" class="btn mt-3">Voir le catalogue complet</a>
        </div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($livres as $l): ?>
            <div class="book-card">
                <div class="book-cover">
                    <?php if (!empty($l['couverture_url'])): ?>
                        <img src="<?= htmlspecialchars($l['couverture_url']) ?>" 
                             alt="<?= htmlspecialchars($l['titre']) ?>" 
                             loading="lazy" 
                             onerror="this.replaceWith(this.nextElementSibling)">
                    <?php endif; ?>
                    <div class="no-cover">
                        <i class="fas fa-book fa-3x"></i>
                        <span style="font-size:0.9rem;"><?= htmlspecialchars(substr($l['titre'],0,20)) ?>…</span>
                    </div>
                </div>
                <div class="book-info">
                    <h3 class="title"><?= htmlspecialchars($l['titre']) ?></h3>
                    <?php if ($l['auteurs']): ?>
                        <div class="auteurs"><?= htmlspecialchars($l['auteurs']) ?></div>
                    <?php endif; ?>
                    <div class="meta">
                        <?php if ($l['annee_publication']): ?>
                            <span>📅 <?= $l['annee_publication'] ?></span>
                        <?php endif; ?>
                        <span class="<?= $l['dispo'] > 0 ? 'dispo' : 'indispo' ?>">
                            <i class="fas fa-circle <?= $l['dispo']>0?'text-success':'text-danger' ?>"></i>
                            <?= $l['dispo'] > 0 ? $l['dispo'].' dispo' : 'Indisponible' ?>
                        </span>
                        <?php if ($l['total_exemplaires'] > 0): ?>
                            <span>/ <?= $l['total_exemplaires'] ?> ex.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination (identique) -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $radius = 2;
            $start = max(1, $search['page'] - $radius);
            $end   = min($total_pages, $search['page'] + $radius);

            $query = http_build_query(array_filter($search, fn($v) => $v !== '' && $v !== 'tous'));

            if ($start > 1): ?>
                <a href="?page=1&<?= $query ?>"><i class="fas fa-angle-double-left"></i></a>
            <?php endif;

            for ($i = $start; $i <= $end; $i++): ?>
                <a href="?page=<?= $i ?>&<?= $query ?>" class="<?= $i == $search['page'] ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor;

            if ($end < $total_pages): ?>
                <a href="?page=<?= $total_pages ?>&<?= $query ?>"><i class="fas fa-angle-double-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<?php require_once 'footer.php'; ?>

</body>
</html>
    