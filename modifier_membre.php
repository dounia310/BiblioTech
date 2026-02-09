<?php
session_start();
require_once 'connexion.php';

// Protection
if (!isset($_SESSION['user_id']) || 
    !in_array($_SESSION['role'], ['bibliothecaire', 'admin', 'super_admin'])) {
    header("Location: index.php");
    exit();
}

$membre_id = (int)($_GET['id'] ?? 0);
if ($membre_id < 1) die("ID invalide");

$message = '';
$error = '';

// Récupérer le membre
try {
    $stmt = $pdo->prepare("SELECT * FROM membres WHERE id = ?");
    $stmt->execute([$membre_id]);
    $membre = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$membre) die("Membre non trouvé");
} catch (PDOException $e) {
    die("Erreur BDD : " . htmlspecialchars($e->getMessage()));
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modifier'])) {
    $nom             = trim($_POST['nom'] ?? '');
    $prenom          = trim($_POST['prenom'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $telephone       = trim($_POST['telephone'] ?? '');
    $adresse         = trim($_POST['adresse'] ?? '');
    $ville           = trim($_POST['ville'] ?? '');
    $code_postal     = trim($_POST['code_postal'] ?? '');
    $pays            = trim($_POST['pays'] ?? 'Maroc');
    $date_naissance  = $_POST['date_naissance'] ?: null;
    $profession      = trim($_POST['profession'] ?? '');
    $institution     = trim($_POST['institution'] ?? '');
    $type_membre     = $_POST['type_membre'] ?? 'etudiant';
    $statut          = $_POST['statut'] ?? 'actif';
    $date_expiration = $_POST['date_expiration'] ?: null;
    $quota_emprunt   = (int)($_POST['quota_emprunt'] ?? 5);
    $categorie_tarif = $_POST['categorie_tarif'] ?? 'gratuit';
    $notes           = trim($_POST['notes'] ?? '');
    $newsletter      = isset($_POST['newsletter']) ? 1 : 0;

    if (empty($nom) || empty($prenom) || empty($email)) {
        $error = "Nom, prénom et email sont obligatoires.";
    } else {
        try {
            $sql = "
                UPDATE membres SET 
                    nom             = ?,
                    prenom          = ?,
                    email           = ?,
                    telephone       = ?,
                    adresse         = ?,
                    ville           = ?,
                    code_postal     = ?,
                    pays            = ?,
                    date_naissance  = ?,
                    profession      = ?,
                    institution     = ?,
                    type_membre     = ?,
                    statut          = ?,
                    date_expiration = ?,
                    quota_emprunt   = ?,
                    categorie_tarif = ?,
                    notes           = ?,
                    newsletter      = ?,
                    updated_at      = NOW()
                WHERE id = ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $nom, $prenom, $email, $telephone,
                $adresse, $ville, $code_postal, $pays,
                $date_naissance, $profession, $institution,
                $type_membre, $statut, $date_expiration,
                $quota_emprunt, $categorie_tarif, $notes,
                $newsletter, $membre_id
            ]);

            $message = "Informations mises à jour avec succès !";

            // Recharger les données
            $stmt = $pdo->prepare("SELECT * FROM membres WHERE id = ?");
            $stmt->execute([$membre_id]);
            $membre = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $error = "Erreur lors de la mise à jour : " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier membre – Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #2b848e;
            --primary-dark: #1e6a72;
            --success: #28a745;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #254c5b;
            --gray: #e9ecef;
            --radius: 10px;
            --shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        body {
            font-family: system-ui, sans-serif;
            background: var(--light);
            color: var(--dark);
            padding: 2rem 1rem;
            min-height: 100vh;
        }
        .container {
            max-width: 720px;
            margin: 0 auto;
        }
        h1 {
            color: var(--primary);
            text-align: center;
            margin-bottom: 2rem;
        }
        .message {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
        }
        .message.success { background: #d4edda; color: #155724; }
        .message.error   { background: #f8d7da; color: #721c24; }
        form {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid var(--gray);
            border-radius: 6px;
            font-size: 1rem;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(43,132,142,0.15);
        }
        .form-actions {
            text-align: center;
            margin-top: 2rem;
        }
        button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.9rem 2rem;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        button:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .links {
            text-align: center;
            margin-top: 2rem;
        }
        .links a {
            margin: 0 1rem;
            color: var(--primary);
            text-decoration: none;
        }
        .links a:hover {
            text-decoration: underline;
        }
        @media (max-width: 576px) {
            button { width: 100%;}
        }
        .danger-zone {
            background: #fff5f5;
            border: 2px solid #dc3545;
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-top: 3rem;
        }
        .danger-zone h3 { color: #dc3545; margin-top: 0; }
        .confirm-input {
            font-family: monospace;
            font-weight: bold;
            color: #dc3545;
        }
    </style>
</head>
<body>

<div class="container">
    <h1><i class="fas fa-user-edit"></i> Modifier le membre #<?= $membre['id'] ?></h1>

    <?php if ($message): ?>
        <div class="message success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <!-- Champs obligatoires -->
        <div class="form-group">
            <label>Nom *</label>
            <input type="text" name="nom" value="<?= htmlspecialchars($membre['nom'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label>Prénom *</label>
            <input type="text" name="prenom" value="<?= htmlspecialchars($membre['prenom'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label>Email *</label>
            <input type="email" name="email" value="<?= htmlspecialchars($membre['email'] ?? '') ?>" required>
        </div>

        <!-- Champs facultatifs / existants -->
        <div class="form-group">
            <label>Téléphone</label>
            <input type="tel" name="telephone" value="<?= htmlspecialchars($membre['telephone'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Adresse</label>
            <textarea name="adresse" rows="2"><?= htmlspecialchars($membre['adresse'] ?? '') ?></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div>
                <label>Ville</label>
                <input type="text" name="ville" value="<?= htmlspecialchars($membre['ville'] ?? '') ?>">
            </div>
            <div>
                <label>Code postal</label>
                <input type="text" name="code_postal" value="<?= htmlspecialchars($membre['code_postal'] ?? '') ?>">
            </div>
            <div>
                <label>Pays</label>
                <input type="text" name="pays" value="<?= htmlspecialchars($membre['pays'] ?? 'Maroc') ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Date de naissance</label>
            <input type="date" name="date_naissance" value="<?= htmlspecialchars($membre['date_naissance'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Profession / Métier</label>
            <input type="text" name="profession" value="<?= htmlspecialchars($membre['profession'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Établissement / Institution</label>
            <input type="text" name="institution" value="<?= htmlspecialchars($membre['institution'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Type de membre</label>
            <select name="type_membre" required>
                <option value="etudiant"   <?= $membre['type_membre'] === 'etudiant'   ? 'selected' : '' ?>>Étudiant</option>
                <option value="enseignant" <?= $membre['type_membre'] === 'enseignant' ? 'selected' : '' ?>>Enseignant</option>
                <option value="chercheur"  <?= $membre['type_membre'] === 'chercheur'  ? 'selected' : '' ?>>Chercheur</option>
                <option value="personnel"  <?= $membre['type_membre'] === 'personnel'  ? 'selected' : '' ?>>Personnel</option>
            </select>
        </div>

        <div class="form-group">
            <label>Statut</label>
            <select name="statut" required>
                <option value="actif"    <?= $membre['statut'] === 'actif'    ? 'selected' : '' ?>>Actif</option>
                <option value="inactif"  <?= $membre['statut'] === 'inactif'  ? 'selected' : '' ?>>Inactif</option>
                <option value="suspendu" <?= $membre['statut'] === 'suspendu' ? 'selected' : '' ?>>Suspendu</option>
                <option value="expire"   <?= $membre['statut'] === 'expire'   ? 'selected' : '' ?>>Expiré</option>
                <option value="banni"    <?= $membre['statut'] === 'banni'    ? 'selected' : '' ?>>Banni</option>
            </select>
        </div>

        <div class="form-group">
            <label>Date d'expiration</label>
            <input type="date" name="date_expiration" value="<?= htmlspecialchars($membre['date_expiration'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label>Quota emprunt max</label>
            <input type="number" name="quota_emprunt" value="<?= (int)($membre['quota_emprunt'] ?? 5) ?>" min="1" max="20">
        </div>

        <div class="form-group">
            <label>Catégorie tarifaire</label>
            <select name="categorie_tarif">
                <option value="gratuit"  <?= $membre['categorie_tarif'] === 'gratuit'  ? 'selected' : '' ?>>Gratuit</option>
                <option value="reduit"   <?= $membre['categorie_tarif'] === 'reduit'   ? 'selected' : '' ?>>Réduit</option>
                <option value="plein"    <?= $membre['categorie_tarif'] === 'plein'    ? 'selected' : '' ?>>Plein</option>
                <option value="premium"  <?= $membre['categorie_tarif'] === 'premium'  ? 'selected' : '' ?>>Premium</option>
            </select>
        </div>

        <div class="form-group">
            <label>Notes internes (admin)</label>
            <textarea name="notes" rows="4"><?= htmlspecialchars($membre['notes'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="newsletter" value="1" <?= !empty($membre['newsletter']) ? 'checked' : '' ?>>
                Abonné à la newsletter
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" name="modifier">Enregistrer les modifications</button>
        </div>
    </form>

    <!-- Liens -->
    <div style="text-align:center; margin-top:2rem;">
        <a href="voir_membre.php?id=<?= $membre_id ?>">Voir fiche complète</a> | 
        <a href="liste_membres.php">Retour liste</a> | 
        <a href="dashboard.php">Tableau de bord</a>
    </div>
</div>

</body>
</html>