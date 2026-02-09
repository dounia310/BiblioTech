<?php
require_once 'connexion.php';
require_once 'header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $contenu = trim($_POST['contenu'] ?? '');
    $date_publication = date('Y-m-d');

    if (empty($titre) || empty($contenu)) {
        $error = "Le titre et le contenu sont obligatoires.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO actualites (titre, contenu, date_publication) VALUES (?, ?, ?)");
            $stmt->execute([$titre, $contenu, $date_publication]);
            $success = "Actualité publiée avec succès !";
        } catch (PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}
?>

<div class="container mt-5">
    <h2>Ajouter une Actualité</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="titre" class="form-label">Titre de l'actualité</label>
            <input type="text" class="form-control" id="titre" name="titre" required>
        </div>
        
        <div class="mb-3">
            <label for="contenu" class="form-label">Contenu</label>
            <textarea class="form-control" id="contenu" name="contenu" rows="8" required></textarea>
        </div>

        <button type="submit" class="btn btn-primary btn-lg">Publier l'actualité</button>
        <a href="actualites.php" class="btn btn-secondary">Retour</a>
    </form>
</div>

<?php require_once 'footer.php'; ?>