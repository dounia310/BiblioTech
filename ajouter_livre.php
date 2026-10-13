<?php
require_once 'connexion.php';
session_start();

// Sécurité : seuls les rôles autorisés peuvent ajouter
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'bibliothecaire', 'super_admin'])) {
    header("Location: index.php");
    exit;
}

$message = '';
$erreur  = '';

// 1. Liste des auteurs existants
$stmt_auteurs = $pdo->query("SELECT id, nom_complet FROM auteurs ORDER BY nom_complet ASC");
$auteurs = $stmt_auteurs->fetchAll(PDO::FETCH_ASSOC);

// 2. Liste des catégories (pour le multi-select)
$stmt_categories = $pdo->query("SELECT id, nom, slug FROM categories ORDER BY nom ASC");
$categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);

// 3. Liste des éditeurs
$stmt_editeurs = $pdo->query("SELECT id, nom FROM editeurs ORDER BY nom ASC");
$editeurs = $stmt_editeurs->fetchAll(PDO::FETCH_ASSOC);

// 4. Liste des collections
$stmt_collections = $pdo->query("SELECT id, nom FROM collections ORDER BY nom ASC");
$collections = $stmt_collections->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre              = trim($_POST['titre'] ?? '');
    $isbn               = trim($_POST['isbn'] ?? '');
    $sous_titre         = trim($_POST['sous_titre'] ?? '');
    $auteur_choice      = $_POST['auteur_choice'] ?? '';
    $nouvel_auteur      = trim($_POST['nouvel_auteur'] ?? '');
    $editeur_id         = (int)($_POST['editeur_id'] ?? 0);
    $collection_id      = (int)($_POST['collection_id'] ?? 0);
    $annee_publication  = !empty($_POST['annee_publication']) ? (int)$_POST['annee_publication'] : null;
    $edition            = trim($_POST['edition'] ?? '');
    $nombre_pages       = !empty($_POST['nombre_pages']) ? (int)$_POST['nombre_pages'] : null;
    $format             = $_POST['format'] ?? 'broche';
    $langue             = $_POST['langue'] ?? 'français';
    $langue_originale   = trim($_POST['langue_originale'] ?? '');
    $description        = trim($_POST['description'] ?? '');
    $resume             = trim($_POST['resume'] ?? '');
    $prix               = !empty($_POST['prix']) ? (float)$_POST['prix'] : null;
    $cote               = trim($_POST['cote'] ?? '');
    $public_cible       = $_POST['public_cible'] ?? 'tout_public';
    $statut_biblio      = $_POST['statut_bibliographique'] ?? 'disponible';
    $categories_post    = $_POST['categories'] ?? [];

    // Validation de base
    if (empty($titre)) {
        $erreur = "Le titre est obligatoire.";
    } elseif ($auteur_choice === 'nouveau' && empty($nouvel_auteur)) {
        $erreur = "Veuillez entrer le nom du nouvel auteur.";
    } elseif ($auteur_choice !== 'nouveau' && empty($auteur_choice)) {
        $erreur = "Veuillez sélectionner un auteur.";
    } else {
        try {
            $pdo->beginTransaction();

            // Gestion de l'auteur
            if ($auteur_choice === 'nouveau') {
                $stmt = $pdo->prepare("SELECT id FROM auteurs WHERE LOWER(nom_complet) = LOWER(?) LIMIT 1");
                $stmt->execute([$nouvel_auteur]);
                $existant = $stmt->fetch();

                if ($existant) {
                    $auteur_id = $existant['id'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO auteurs (nom_complet, created_at) VALUES (?, NOW())");
                    $stmt->execute([$nouvel_auteur]);
                    $auteur_id = $pdo->lastInsertId();
                }
            } else {
                $auteur_id = (int)$auteur_choice;
            }

            // Insertion du livre
            $stmt = $pdo->prepare("
                INSERT INTO livres 
                (isbn, titre, auteur_id, sous_titre, editeur_id, collection_id, annee_publication, edition,
                 nombre_pages, format, langue, langue_originale, description, resume, prix, cote,
                 public_cible, statut_bibliographique, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $isbn ?: null,
                $titre,
                $auteur_id,
                $sous_titre ?: null,
                $editeur_id ?: null,
                $collection_id ?: null,
                $annee_publication,
                $edition ?: null,
                $nombre_pages,
                $format,
                $langue,
                $langue_originale ?: null,
                $description,
                $resume,
                $prix,
                $cote ?: null,
                $public_cible,
                $statut_biblio
            ]);

            $livre_id = $pdo->lastInsertId();

            // 1. Genre principal = première catégorie sélectionnée (ou vide)
            if (!empty($categories_post)) {
                $genre_principal_id = (int)$categories_post[0];
                $stmt_genre = $pdo->prepare("UPDATE livres SET genre_principal = (SELECT nom FROM categories WHERE id = ?) WHERE id = ?");
                $stmt_genre->execute([$genre_principal_id, $livre_id]);
            }

            // 2. Catégories multiples (liaison)
            if (!empty($categories_post) && is_array($categories_post)) {
                $stmt_cat = $pdo->prepare("INSERT IGNORE INTO livre_categories (livre_id, categorie_id, principal, created_at) VALUES (?, ?, ?, NOW())");
                foreach ($categories_post as $cat_id) {
                    $cat_id = (int)$cat_id;
                    if ($cat_id > 0) {
                        $is_principal = ($cat_id == $genre_principal_id) ? 1 : 0;
                        $stmt_cat->execute([$livre_id, $cat_id, $is_principal]);
                    }
                }
            }

            $pdo->commit();

            $_SESSION['message'] = "Livre ajouté avec succès ! (ID : $livre_id)";
            header("Location: liste_livres.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $erreur = "Erreur lors de l'ajout : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un livre – Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Select2 pour un beau multi-select -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <style>
        :root {
             --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --primary-dark: #152a45;
            --success: #10b981;
        }
        body {
            font-family: system-ui, sans-serif;
            background: #f8f9fa;
            padding: 2rem;
            max-width: 1000px;
            margin: 0 auto;
        }
        h1 { color: var(--primary); text-align: center; }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
        }
        textarea { min-height: 120px; }
        button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.1rem;
        }
        button:hover { background: #1e6a72; }
        .error { color: var(--danger); margin: 1rem 0; padding: 1rem; background: #ffebee; border-radius: 6px; }
        .success { color: var(--success); margin: 1rem 0; padding: 1rem; background: #e8f5e9; border-radius: 6px; }
        #nouveau-auteur { display: none; }
        .select2-container { width: 100% !important; }
        small { color: #666; font-size: 0.9rem; }
    </style>
</head>
<body>

<h1><i class="fas fa-book-medical"></i> Ajouter un nouveau livre</h1>

<?php if (!empty($_SESSION['message'])): ?>
    <div class="success"><?= htmlspecialchars($_SESSION['message']) ?></div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<?php if ($erreur): ?>
    <div class="error"><?= htmlspecialchars($erreur) ?></div>
<?php endif; ?>

<form method="POST">
    <!-- Titre -->
    <div class="form-group">
        <label for="titre">Titre du livre *</label>
        <input type="text" id="titre" name="titre" required value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>">
    </div>

    <!-- Auteur -->
    <div class="form-group">
        <label for="auteur_choice">Auteur *</label>
        <select name="auteur_choice" id="auteur_choice" required>
            <option value="">-- Choisir un auteur --</option>
            <?php foreach ($auteurs as $a): ?>
                <option value="<?= $a['id'] ?>" <?= ($_POST['auteur_choice'] ?? '') == $a['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($a['nom_complet']) ?>
                </option>
            <?php endforeach; ?>
            <option value="nouveau" <?= ($_POST['auteur_choice'] ?? '') === 'nouveau' ? 'selected' : '' ?>>
                + Nouvel auteur
            </option>
        </select>
    </div>

    <div class="form-group" id="nouveau-auteur">
        <label for="nouvel_auteur">Nom complet du nouvel auteur *</label>
        <input type="text" id="nouvel_auteur" name="nouvel_auteur" 
               value="<?= htmlspecialchars($_POST['nouvel_auteur'] ?? '') ?>"
               placeholder="Ex : Victor Hugo">
    </div>

    <!-- Catégories (multi) -->
    <div class="form-group">
        <label for="categories">Catégories / Genres (choisir plusieurs avec Ctrl/Cmd)</label>
        <select name="categories[]" id="categories" multiple size="8">
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" 
                        <?= in_array($cat['id'], $_POST['categories'] ?? []) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['nom']) ?> (<?= htmlspecialchars($cat['slug']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <small>(La première catégorie sélectionnée deviendra le genre principal)</small>
    </div>

    <!-- ISBN, Éditeur, Collection, Année, etc. -->
    <div class="form-group">
        <label for="isbn">ISBN</label>
        <input type="text" id="isbn" name="isbn" value="<?= htmlspecialchars($_POST['isbn'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="editeur_id">Éditeur</label>
        <select name="editeur_id" id="editeur_id">
            <option value="">-- Aucun / Inconnu --</option>
            <?php foreach ($editeurs as $ed): ?>
                <option value="<?= $ed['id'] ?>" <?= ($_POST['editeur_id'] ?? '') == $ed['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ed['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="collection_id">Collection</label>
        <select name="collection_id" id="collection_id">
            <option value="">-- Aucune --</option>
            <?php foreach ($collections as $col): ?>
                <option value="<?= $col['id'] ?>" <?= ($_POST['collection_id'] ?? '') == $col['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($col['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label for="annee_publication">Année de publication</label>
        <input type="number" id="annee_publication" name="annee_publication" min="1400" max="<?= date('Y') + 1 ?>" 
               value="<?= htmlspecialchars($_POST['annee_publication'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="nombre_pages">Nombre de pages</label>
        <input type="number" id="nombre_pages" name="nombre_pages" min="1" 
               value="<?= htmlspecialchars($_POST['nombre_pages'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="format">Format</label>
        <select name="format" id="format">
            <option value="poche" <?= ($_POST['format'] ?? '') === 'poche' ? 'selected' : '' ?>>Poche</option>
            <option value="broche" <?= ($_POST['format'] ?? 'broche') === 'broche' ? 'selected' : '' ?>>Broché</option>
            <option value="relié" <?= ($_POST['format'] ?? '') === 'relié' ? 'selected' : '' ?>>Relié</option>
            <option value="grand_format" <?= ($_POST['format'] ?? '') === 'grand_format' ? 'selected' : '' ?>>Grand format</option>
        </select>
    </div>

    <!-- Description et résumé (plus longs) -->
    <div class="form-group">
        <label for="description">Description détaillée</label>
        <textarea name="description" id="description"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label for="resume">Résumé / 4e de couverture</label>
        <textarea name="resume" id="resume"><?= htmlspecialchars($_POST['resume'] ?? '') ?></textarea>
    </div>

    <!-- Prix et Cote -->
    <div class="form-group">
        <label for="prix">Prix public (en DH)</label>
        <input type="number" step="0.01" id="prix" name="prix" value="<?= htmlspecialchars($_POST['prix'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="cote">Cote bibliothèque</label>
        <input type="text" id="cote" name="cote" value="<?= htmlspecialchars($_POST['cote'] ?? '') ?>" placeholder="Ex : R CAM 1942">
    </div>

    <!-- Public cible et Statut -->
    <div class="form-group">
        <label for="public_cible">Public cible</label>
        <select name="public_cible" id="public_cible">
            <option value="tout_public" <?= ($_POST['public_cible'] ?? 'tout_public') === 'tout_public' ? 'selected' : '' ?>>Tout public</option>
            <option value="jeunesse" <?= ($_POST['public_cible'] ?? '') === 'jeunesse' ? 'selected' : '' ?>>Jeunesse</option>
            <option value="adulte" <?= ($_POST['public_cible'] ?? '') === 'adulte' ? 'selected' : '' ?>>Adulte</option>
            <option value="universitaire" <?= ($_POST['public_cible'] ?? '') === 'universitaire' ? 'selected' : '' ?>>Universitaire</option>
        </select>
    </div>

    <div class="form-group">
        <label for="statut_bibliographique">Statut bibliographique</label>
        <select name="statut_bibliographique" id="statut_bibliographique">
            <option value="disponible" <?= ($_POST['statut_bibliographique'] ?? 'disponible') === 'disponible' ? 'selected' : '' ?>>Disponible</option>
            <option value="epuise" <?= ($_POST['statut_bibliographique'] ?? '') === 'epuise' ? 'selected' : '' ?>>Épuisé</option>
            <option value="nouveaute" <?= ($_POST['statut_bibliographique'] ?? '') === 'nouveaute' ? 'selected' : '' ?>>Nouveauté</option>
            <option value="a_paraitre" <?= ($_POST['statut_bibliographique'] ?? '') === 'a_paraitre' ? 'selected' : '' ?>>À paraître</option>
        </select>
    </div>

    <button type="submit"><i class="fas fa-plus-circle"></i> Ajouter le livre</button>
</form>

<a href="liste_livres.php" style="display:inline-block; margin-top:1.5rem; color:var(--primary); font-weight:bold;">
    ← Retour au catalogue
</a>

<script>
    // Afficher/masquer le champ nouvel auteur
    const selectAuteur = document.getElementById('auteur_choice');
    const divNouveau = document.getElementById('nouveau-auteur');
    
    function toggleNouveauAuteur() {
        divNouveau.style.display = (selectAuteur.value === 'nouveau') ? 'block' : 'none';
        document.getElementById('nouvel_auteur').toggleAttribute('required', selectAuteur.value === 'nouveau');
    }
    
    selectAuteur.addEventListener('change', toggleNouveauAuteur);
    toggleNouveauAuteur(); // Au chargement

    // Initialiser Select2 pour les catégories
    $(document).ready(function() {
        $('#categories').select2({
            placeholder: "Sélectionnez une ou plusieurs catégories",
            allowClear: true,
            closeOnSelect: false
        });
    });
</script>
<style>
    .notifications-bell {
        position: relative;
        cursor: pointer;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.2s;
    }

    .notifications-bell:hover {
        background: var(--primary);
        color: white;
    }

    .notification-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: var(--danger);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 12px;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .notifications-dropdown {
        position: absolute;
        top: 60px;
        right: 20px;
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        box-shadow: var(--shadow-md);
        width: 320px;
        max-height: 400px;
        overflow-y: auto;
        z-index: 1000;
    }

    .notifications-dropdown ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .notifications-dropdown li {
        padding: 12px 16px;
        border-bottom: 1px solid var(--gray-200);
    }

    .notifications-dropdown li.non-lue {
        background: rgba(233, 236, 239, 0.5);
        font-weight: 500;
    }

    .notifications-dropdown li a {
        text-decoration: none;
        color: var(--gray-700);
        display: block;
    }

    .notifications-dropdown li small {
        color: var(--gray-500);
        font-size: 0.85rem;
    }

    .dropdown-footer {
        padding: 12px;
        text-align: center;
        border-top: 1px solid var(--gray-200);
    }

    .dropdown-footer a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
    }
</style>

<script>
    function toggleNotifications() {
        const dropdown = document.getElementById('notifications-dropdown');
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    }

    // Fermer le dropdown si clic ailleurs
    document.addEventListener('click', function(e) {
        const bell = document.querySelector('.notifications-bell');
        const dropdown = document.getElementById('notifications-dropdown');
        if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

    // Marquer comme lu (appel AJAX simple - à améliorer si besoin)
    function marquerLue(id) {
        fetch('marquer_lue.php?id=' + id)
            .then(() => {
                // Option : rafraîchir le badge ou supprimer la classe non-lue
            });
    }
</script>
</body>
</html>