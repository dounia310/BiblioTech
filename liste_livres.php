<?php
require_once 'connexion.php';
session_start();

// Paramètres GET
$search = trim($_GET['search'] ?? '');
$sort   = $_GET['sort']   ?? 'titre_asc';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

// Liste blanche des tris autorisés
$tri_options = [
    'titre_asc'     => ['sql' => 'l.titre ASC',           'label' => 'Titre (A → Z)'],
    'titre_desc'    => ['sql' => 'l.titre DESC',          'label' => 'Titre (Z → A)'],
    'recent_insere' => ['sql' => 'l.id DESC',             'label' => 'Plus récents insérés'],
    'ancien_insere' => ['sql' => 'l.id ASC',              'label' => 'Plus anciens insérés'],
    'populaire'     => ['sql' => 'nb_emprunts DESC',      'label' => 'Plus populaire'],
];

$order_by = $tri_options[$sort]['sql'] ?? 'l.titre ASC';

// Construction de la clause WHERE
$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE 
        l.titre LIKE ? 
        OR l.isbn LIKE ? 
        OR a.nom_complet LIKE ?";
    $like = "%$search%";
    $params = [$like, $like, $like];
}

// 1. Compter le total pour la pagination
$count_sql = "
    SELECT COUNT(DISTINCT l.id) as total
    FROM livres l
    LEFT JOIN auteurs a ON l.auteur_id = a.id
    $where
";
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total = $stmt_count->fetchColumn();

$totalPages = max(1, ceil($total / $perPage));
$page = min($page, $totalPages); // Sécurité

$offset = ($page - 1) * $perPage;

// 2. Requête principale avec LIMIT et OFFSET
$sql = "
    SELECT 
        l.id,
        l.titre,
        l.statut_bibliographique,
        COUNT(e.id) AS total_exemplaires,
        COUNT(CASE WHEN e.statut = 'disponible' THEN 1 END) AS exemplaires_disponibles,
        COUNT(em.id) AS nb_emprunts
    FROM livres l
    LEFT JOIN auteurs a ON l.auteur_id = a.id
    LEFT JOIN exemplaires e ON l.id = e.livre_id
    LEFT JOIN emprunts em ON e.id = em.exemplaire_id
    $where
    GROUP BY l.id
    ORDER BY $order_by
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$livres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fonction pour générer les liens de pagination en gardant search + sort
function pagination_link($p) {
    global $search, $sort;
    $params = ['page' => $p];
    if ($search !== '') $params['search'] = $search;
    if ($sort !== 'titre_asc') $params['sort'] = $sort;
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue des livres – Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #2b848e;
            --primary-dark: #1e6a72;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #254c5b;
            --gray: #e9ecef;
            --radius: 10px;
            --shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        body {
            font-family: system-ui, sans-serif;
            background: #f8f9fa;
            color: #333;
            margin: 0;
            padding: 1.5rem;
            line-height: 1.6;
        }

        h1 {
            color: #2b848e;
            text-align: center;
            margin-bottom: 1.8rem;
        }

        .search-form {
            max-width: 900px;
            margin: 0 auto 2rem;
            text-align: center;
        }

        .search-input {
            width: 60%;
            padding: 12px 16px;
            font-size: 1.1rem;
            border: 2px solid #ddd;
            border-radius: 8px;
        }

        .search-btn {
            padding: 12px 24px;
            background: #2b848e;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin-left: 8px;
        }

        .search-btn:hover { background: #1e6a72; }

        .clear { 
            color: #e74c3c; 
            margin-left: 12px; 
            text-decoration: none; 
        }

        .container { 
            max-width: 1450px; 
            margin: 0 auto; 
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            border-radius: 8px;
            overflow: hidden;
        }

        th, td { 
            padding: 14px 12px; 
            text-align: left; 
            border-bottom: 1px solid #eee; 
        }

        th { 
            background: #2b848e; 
            color: white; 
        }

        tr:hover { 
            background: #f0f8f8; 
        }

        .disponible { 
            color: #27ae60; 
            font-weight: bold; 
        }

        .epuise { 
            color: #e74c3c; 
            font-weight: bold; 
        }

        .actions { 
            white-space: nowrap; 
        }

        .btn {
            padding: 7px 12px;
            border: none;
            border-radius: 6px;
            color: white;
            font-size: 0.95rem;
            cursor: pointer;
            margin: 0 3px;
            transition: all 0.2s;
        }

        .btn-details  { background: #6c757d; }
        .btn-edit     { background: #007bff; }
        .btn-add      { background: #fd7e14; }
        .btn-delete   { background: #dc3545; }
        .btn:hover    { opacity: 0.92; transform: scale(1.04); }

        .no-result {
            text-align: center;
            padding: 4rem 1rem;
            color: #6c757d;
            font-size: 1.3rem;
        }

        .admin-tools {
            text-align: center;
            margin-top: 2rem;
        }

        .admin-btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 0 10px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            color: white;
        }

        .add-book { background: #28a745; }
        .home     { background: #6c757d; }

        @media (max-width: 992px) {
            .actions { display: flex; flex-wrap: wrap; gap: 6px; justify-content: center; }
            .btn { margin: 4px 2px; }
        }

        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { border: 1px solid var(--gray); margin-bottom: 1.5rem; border-radius: var(--radius); }
            td { border: none; position: relative; padding-left: 50%; min-height: 2.8rem; }
            td:before {
                position: absolute;
                top: 14px;
                left: 16px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: bold;
                color: var(--dark);
            }
            td:nth-of-type(1):before { content: "Titre"; }
            td:nth-of-type(2):before { content: "Exemplaires"; }
            td:nth-of-type(3):before { content: "Dispo"; }
            td:nth-of-type(4):before { content: "Statut"; }
            td:nth-of-type(5):before { content: "Actions"; }
        }

        .filters {
            max-width: 900px;
            margin: 0 auto 1.5rem;
            text-align: center;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1rem;
        }

        .sort-select {
            padding: 10px 14px;
            font-size: 1rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            min-width: 240px;
            background: white;
        }

        .pagination {
            text-align: center;
            margin: 2.5rem 0 1.5rem;
            font-size: 1.1rem;
        }

        .pagination a,
        .pagination span {
            padding: 8px 14px;
            margin: 0 4px;
            border-radius: 6px;
            text-decoration: none;
            color: #2b848e;
            background: #fff;
            border: 1px solid #ddd;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: #2b848e;
            color: white;
            border-color: #2b848e;
        }

        .pagination .current {
            background: #2b848e;
            color: white;
            border-color: #2b848e;
            font-weight: bold;
        }

        .pagination .disabled {
            color: #aaa;
            background: #f8f9fa;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>📚 Liste des livres</h1>

    <!-- Formulaire de recherche (inchangé) -->
    <div class="search-form">
        <form method="GET">
            <input type="text" name="search" class="search-input"
                   placeholder="Rechercher par titre, ISBN ou auteur..."
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="search-btn"><i class="fas fa-search"></i> Rechercher</button>
            <?php if ($search !== ''): ?>
                <a href="liste_livres.php?sort=<?= urlencode($sort) ?>" class="clear">✖ Effacer</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Sélection du tri (inchangé) -->
    <div class="filters">
        <form method="GET">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <select name="sort" class="sort-select" onchange="this.form.submit()">
                <?php foreach ($tri_options as $key => $opt): ?>
                    <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>>
                        <?= htmlspecialchars($opt['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (count($livres) > 0): ?>

        <p style="text-align:center; color:#555; margin:1rem 0;">
            <?= $total ?> livre<?= $total > 1 ? 's' : '' ?> trouvé<?= $total > 1 ? 's' : '' ?>
            <?= $search ? " pour « " . htmlspecialchars($search) . " »" : "" ?>
            — page <?= $page ?> sur <?= $totalPages ?>
        </p>

        <table>
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Exemplaires</th>
                    <th>Dispo.</th>
                    <th>Statut</th>
                    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'bibliothecaire', 'super_admin'])): ?>
                        <th>Actions Admin</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($livres as $l): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($l['titre']) ?></strong></td>
                    <td><?= $l['total_exemplaires'] ?></td>
                    <td class="<?= $l['exemplaires_disponibles'] > 0 ? 'disponible' : 'epuise' ?>">
                        <?= $l['exemplaires_disponibles'] ?>
                    </td>
                    <td><?= htmlspecialchars($l['statut_bibliographique'] ?: '—') ?></td>

                    <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin','bibliothecaire','super_admin'])): ?>
                    <td class="actions">
                        <!-- tes boutons actions (inchangés) -->
                        <button class="btn btn-details" onclick="location.href='details_livre.php?id=<?= $l['id'] ?>'">
                            <i class="fas fa-eye"></i> Détails
                        </button><button class="btn btn-edit" onclick="location.href='modifier_livre.php?id=<?= $l['id'] ?>'">
                            <i class="fas fa-edit"></i> Modifier
                        </button>
                        <button class="btn btn-add" onclick="location.href='ajouter_exemplaire.php?id=<?= $l['id'] ?>'">
                            <i class="fas fa-plus"></i> Ajouter exemplaire(s)
                        </button>
                        <button class="btn btn-delete" onclick="if(confirm('Supprimer « <?= addslashes($l['titre']) ?> » ?')) location.href='supprimer_livre.php?id=<?= $l['id'] ?>';">
                            <i class="fas fa-trash-alt"></i> Supprimer
                        </button>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="<?= pagination_link($page - 1) ?>">« Précédent</a>
            <?php else: ?>
                <span class="disabled">« Précédent</span>
            <?php endif; ?>

            <?php
            $range = 2; // pages à afficher de chaque côté de la page courante
            $start = max(1, $page - $range);
            $end   = min($totalPages, $page + $range);

            if ($start > 1) echo '<a href="' . pagination_link(1) . '">1</a><span>...</span>';
            
            for ($i = $start; $i <= $end; $i++):
            ?>
                <a href="<?= pagination_link($i) ?>" class="<?= $i === $page ? 'current' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($end < $totalPages) echo '<span>...</span><a href="' . pagination_link($totalPages) . '">' . $totalPages . '</a>'; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= pagination_link($page + 1) ?>">Suivant »</a>
            <?php else: ?>
                <span class="disabled">Suivant »</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="no-result">
            <i class="fas fa-book-open fa-3x" style="opacity:0.4; margin-bottom:1rem;"></i><br>
            <?php if ($search !== ''): ?>
                Aucun résultat pour « <?= htmlspecialchars($search) ?> ».<br>
                <small>Essayez d'autres termes ou <a href="liste_livres.php">voir tout</a>.</small>
            <?php else: ?>
                Aucun livre pour le moment.<br>
                <small>Ajoutez-en un (admin).</small>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="admin-tools">
        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin','bibliothecaire','super_admin'])): ?>
            <a href="ajouter_livre.php" class="admin-btn add-book">+ Ajouter un nouveau livre</a>
        <?php endif; ?>
        <a href="index.php" class="admin-btn home">← Retour à l'accueil</a>
    </div>
</div>

</body>
</html>