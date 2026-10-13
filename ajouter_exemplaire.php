<?php
session_start();
require_once 'connexion.php';

// Protection
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'bibliothecaire', 'super_admin'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$success = false;
$livres = [];
$livre_preselectionne = null;

// Récupérer les options pour les filtres de recherche
$annees = $pdo->query("SELECT DISTINCT annee_publication FROM livres WHERE annee_publication IS NOT NULL ORDER BY annee_publication DESC")->fetchAll(PDO::FETCH_COLUMN);
$langues = $pdo->query("SELECT DISTINCT langue FROM livres WHERE langue IS NOT NULL ORDER BY langue")->fetchAll(PDO::FETCH_COLUMN);
$genres = $pdo->query("SELECT DISTINCT genre_principal FROM livres WHERE genre_principal IS NOT NULL ORDER BY genre_principal")->fetchAll(PDO::FETCH_COLUMN);
$statuts = ['disponible', 'epuise', 'nouveaute', 'a_paraitre', 'hors_catalogue'];

// 1. Chargement direct si ?id=XXX est présent
$livre_id_url = (int)($_GET['id'] ?? 0);
if ($livre_id_url > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                l.id, l.isbn, l.titre, l.annee_publication, l.langue, l.genre_principal,
                COALESCE(a.nom_complet, a.nom, CONCAT(a.prenom, ' ', a.nom)) AS auteur,
                (SELECT COUNT(*) FROM exemplaires WHERE livre_id = l.id) AS nb_exemplaires
            FROM livres l
            LEFT JOIN auteurs a ON l.auteur_id = a.id
            WHERE l.id = ?
        ");
        $stmt->execute([$livre_id_url]);
        $livre_preselectionne = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$livre_preselectionne) {
            $message = "Livre ID $livre_id_url introuvable.";
        }
    } catch (PDOException $e) {
        $message = "Erreur chargement livre : " . htmlspecialchars($e->getMessage());
    }
}

// 2. Recherche avancée (uniquement si PAS de livre pré-sélectionné)
if (!$livre_preselectionne && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rechercher'])) {
    $titre_isbn_auteur = trim($_POST['recherche'] ?? '');
    $genre = $_POST['genre'] ?? '';
    $annee = $_POST['annee'] ?? '';
    $langue = $_POST['langue'] ?? '';
    $statut = $_POST['statut'] ?? '';

    $sql = "
        SELECT 
            l.id, l.isbn, l.titre, l.annee_publication, l.langue, l.genre_principal,
            COALESCE(a.nom_complet, a.nom, CONCAT(a.prenom, ' ', a.nom)) AS auteur,
            l.statut_bibliographique,
            (SELECT COUNT(*) FROM exemplaires WHERE livre_id = l.id) AS nb_exemplaires
        FROM livres l
        LEFT JOIN auteurs a ON l.auteur_id = a.id
        WHERE 1=1
    ";
    $params = [];

    if ($titre_isbn_auteur !== '') {
        $sql .= " AND (l.titre LIKE ? OR l.isbn LIKE ? OR COALESCE(a.nom_complet, a.nom, CONCAT(a.prenom, ' ', a.nom)) LIKE ?)";
        $like = "%$titre_isbn_auteur%";
        $params = [$like, $like, $like];
    }
    if ($genre !== '') {
        $sql .= " AND l.genre_principal = ?";
        $params[] = $genre;
    }
    if ($annee !== '') {
        $sql .= " AND l.annee_publication = ?";
        $params[] = $annee;
    }
    if ($langue !== '') {
        $sql .= " AND l.langue = ?";
        $params[] = $langue;
    }
    if ($statut !== '') {
        $sql .= " AND l.statut_bibliographique = ?";
        $params[] = $statut;
    }

    $sql .= " LIMIT 15";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $livres = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($livres)) {
            $message = "Aucun livre ne correspond à vos critères.";
        }
    } catch (PDOException $e) {
        $message = "Erreur lors de la recherche : " . htmlspecialchars($e->getMessage());
    }
}

// 3. Ajout de plusieurs exemplaires
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_exemplaire'])) {
    $livre_id         = (int)($_POST['livre_id'] ?? 0);
    $nombre_exemplaires = max(1, (int)($_POST['nombre_exemplaires'] ?? 1)); // minimum 1
    $code_barre_base  = trim($_POST['code_barre_base'] ?? '');
    $code_inventaire  = trim($_POST['code_inventaire'] ?? '');
    $localisation     = trim($_POST['localisation'] ?? '');
    $section          = trim($_POST['section'] ?? '');
    $rayon            = trim($_POST['rayon'] ?? '');

    if ($livre_id < 1 || empty($code_barre_base)) {
        $message = "Données incomplètes (livre ou base du code barre manquant).";
    } else {
        try {
            $pdo->beginTransaction();
            $exemplaires_ajoutes = 0;
            $codes_barres_utilises = [];

            // Vérifier que le code_barre de base n'existe pas déjà (pour éviter doublons massifs)
            $stmt_check = $pdo->prepare("SELECT code_barre FROM exemplaires WHERE code_barre LIKE ?");
            $stmt_check->execute(["$code_barre_base%"]);
            while ($row = $stmt_check->fetch()) {
                $codes_barres_utilises[] = $row['code_barre'];
            }

            for ($i = 1; $i <= $nombre_exemplaires; $i++) {
                // Générer un code barre unique : base + suffixe 001, 002, etc.
                $suffixe = str_pad($i, 3, '0', STR_PAD_LEFT);
                $code_barre = $code_barre_base . '-' . $suffixe;

                // Vérifier unicité
                if (in_array($code_barre, $codes_barres_utilises)) {
                    throw new Exception("Le code barre $code_barre existe déjà.");
                }

                $stmt = $pdo->prepare("
                    INSERT INTO exemplaires 
                    (livre_id, code_barre, code_inventaire, etat, date_acquisition, 
                     localisation, section, rayon, statut)
                    VALUES (?, ?, ?, 'neuf', CURDATE(), ?, ?, ?, 'disponible')
                ");
                $stmt->execute([
                    $livre_id,
                    $code_barre,
                    $code_inventaire ? $code_inventaire . '-' . $suffixe : null,
                    $localisation,
                    $section,
                    $rayon
                ]);

                $exemplaire_id = $pdo->lastInsertId();
                $codes_barres_utilises[] = $code_barre;

                // Log audit (optionnel)
                if (isset($_SESSION['user_id'])) {
                    $pdo->prepare("
                        INSERT INTO logs_audit (table_name, record_id, action, user_id, user_type)
                        VALUES ('exemplaires', ?, 'INSERT', ?, 'staff')
                    ")->execute([$exemplaire_id, $_SESSION['user_id']]);
                }

                $exemplaires_ajoutes++;
            }

            $pdo->commit();

            $success = true;
            $message = "<strong>Succès !</strong><br>$exemplaires_ajoutes exemplaire(s) ajouté(s) avec succès pour le livre ID $livre_id.<br>";
            $message .= "Codes-barres créés : $code_barre_base-001 à $code_barre_base-" . str_pad($exemplaires_ajoutes, 3, '0', STR_PAD_LEFT);

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Erreur lors de l'ajout : " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un ou plusieurs exemplaires - Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
           --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --primary-dark: #152a45;
            --success: #10b981;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #254c5b;
            --gray: #e9ecef;
            --radius: 8px;
            --shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        body { font-family: system-ui, sans-serif; background: var(--light); color: var(--dark); margin: 0; padding: 2rem 1rem; }
        .container { max-width: 900px; margin: 0 auto; }
        h1, h2, h3 { color: var(--primary); }
        .message { padding: 1rem; border-radius: var(--radius); margin: 1rem 0; text-align: center; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .search-form, .add-form, .preselected-book {
            background: white;
            padding: 1.8rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        .form-row { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.2rem; }
        .form-group { flex: 1; min-width: 220px; }
        label { display: block; margin-bottom: 0.4rem; font-weight: 600; }
        input, select { width: 100%; padding: 0.7rem; border: 1px solid var(--gray); border-radius: 6px; }
        button { background: var(--primary); color: white; border: none; padding: 0.8rem 1.6rem; border-radius: 6px; font-weight: 600; cursor: pointer; }
        button:hover { background: var(--primary-dark); }
        .livre-item { background: white; border: 1px solid var(--gray); padding: 1rem; margin-bottom: 1rem; border-radius: var(--radius); cursor: pointer; transition: all 0.2s; }
        .livre-item:hover, .livre-selected { border-color: var(--primary); background: #e7f3ff; }
        #btn-ajouter:disabled { background: #ccc; cursor: not-allowed; }
        .preselected-title { margin: 0 0 0.8rem; font-size: 1.4rem; }
        .back-to-search { display: inline-block; margin-top: 1rem; color: var(--primary); text-decoration: none; }
        .back-to-search:hover { text-decoration: underline; }
        .note { font-size: 0.9rem; color: #555; margin-top: 0.3rem; }
    </style>
</head>
<body>

<div class="container">
    <h1><i class="fas fa-plus-circle"></i> Ajouter un ou plusieurs exemplaires</h1>

    <?php if ($message): ?>
        <div class="message <?= $success ? 'success' : 'error' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if ($livre_preselectionne): ?>
        <!-- Mode direct : livre déjà choisi via ?id= -->
        <div class="preselected-book">
            <h2 class="preselected-title"><?= htmlspecialchars($livre_preselectionne['titre']) ?></h2>
            <p>
                <strong>Auteur :</strong> <?= htmlspecialchars($livre_preselectionne['auteur'] ?: '—') ?><br>
                <strong>ISBN :</strong> <?= htmlspecialchars($livre_preselectionne['isbn'] ?: '—') ?><br>
                <strong>Année :</strong> <?= htmlspecialchars($livre_preselectionne['annee_publication'] ?: '—') ?><br>
                <strong>Exemplaires existants :</strong> <?= $livre_preselectionne['nb_exemplaires'] ?>
            </p>
            <a href="ajouter_exemplaire.php" class="back-to-search">← Choisir un autre livre</a>
        </div>

        <!-- Formulaire d'ajout multiple -->
        <div class="add-form">
            <h3>Ajouter exemplaire(s) pour ce livre</h3>
            <form method="POST" id="form-exemplaire">
                <input type="hidden" name="livre_id" value="<?= $livre_preselectionne['id'] ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre d'exemplaires à ajouter *</label>
                        <input type="number" name="nombre_exemplaires" min="1" max="200" value="1" required autofocus>
                        <div class="note">Vous pouvez ajouter jusqu'à 200 exemplaires d'un coup</div>
                    </div>

                    <div class="form-group">
                        <label>Code barre de base (sera incrémenté) *</label>
                        <input type="text" name="code_barre_base" required pattern="[A-Za-z0-9-]+" 
                               value="EX-<?= str_pad($livre_preselectionne['id'], 4, '0', STR_PAD_LEFT) ?>-<?= date('ymd') ?>" 
                               placeholder="Ex : EX-0123-260205">
                        <div class="note">Exemple : EX-0123-260205 → deviendra EX-0123-260205-001, -002, etc.</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Code d'inventaire de base (optionnel)</label>
                        <input type="text" name="code_inventaire" 
                               value="INV-<?= str_pad($livre_preselectionne['id'], 4, '0', STR_PAD_LEFT) ?>-<?= date('Y') ?>">
                        <div class="note">Sera incrémenté automatiquement (ex : -001, -002...)</div>
                    </div>

                    <div class="form-group">
                        <label>Localisation *</label>
                        <select name="localisation" required>
                            <option value="">-- Choisir --</option>
                            <option value="Rez-de-chaussée">Rez-de-chaussée</option>
                            <option value="Premier étage">Premier étage</option>
                            <option value="Espace jeunesse">Espace jeunesse</option>
                            <option value="Réserve">Réserve</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Section</label>
                        <input type="text" name="section" placeholder="Ex : Littérature française">
                    </div>

                    <div class="form-group">
                        <label>Rayon</label>
                        <input type="text" name="rayon" placeholder="Ex : R pour Roman">
                    </div>
                </div>

                <button type="submit" name="ajouter_exemplaire" id="btn-ajouter">
                    <i class="fas fa-plus"></i> Ajouter les exemplaires
                </button>
            </form>
        </div>

    <?php else: ?>
        <!-- Mode recherche avancée (inchangé) -->
        <div class="search-form">
            <h2>Étape 1 : Trouver le livre</h2>
            <!-- ... ton formulaire de recherche existant reste identique ... -->
        </div>

        <?php if (!empty($livres)): ?>
            <h3>Résultats (cliquez sur un livre pour ajouter des exemplaires)</h3>
            <?php foreach ($livres as $livre): ?>
                <a href="ajouter_exemplaire.php?id=<?= $livre['id'] ?>" class="livre-item">
                    <strong><?= htmlspecialchars($livre['titre']) ?></strong><br>
                    <small>
                        Auteur : <?= htmlspecialchars($livre['auteur'] ?: '—') ?><br>
                        ISBN : <?= htmlspecialchars($livre['isbn'] ?: '—') ?> | 
                        Année : <?= htmlspecialchars($livre['annee_publication'] ?: '—') ?><br>
                        Exemplaires existants : <strong><?= $livre['nb_exemplaires'] ?></strong>
                    </small>
                </a>
            <?php endforeach; ?>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rechercher'])): ?>
            <p style="text-align:center; color:#721c24;">Aucun livre ne correspond à votre recherche.</p>
        <?php endif; ?>

    <?php endif; ?>

    <p style="text-align:center; margin-top:2rem;">
        <a href="dashboard.php">← Retour au tableau de bord</a>
    </p>
</div>

</body>
</html>