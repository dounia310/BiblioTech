<?php
require_once 'connexion.php';


$livre_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($livre_id <= 0) {
    header('Location: liste_livres.php?error=invalid_id');
    exit;
}

// Vérifier si le livre existe
$stmt = $pdo->prepare("SELECT titre FROM livres WHERE id = ?");
$stmt->execute([$livre_id]);
$livre = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$livre) {
    header('Location: liste_livres.php?error=livre_introuvable');
    exit;
}

// Suppression effective (POST pour CSRF + confirmation)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // À améliorer : ajouter vérification CSRF ici plus tard

    try {
        $pdo->beginTransaction();

        // 1. Supprimer les exemplaires liés
        $stmt = $pdo->prepare("DELETE FROM exemplaires WHERE livre_id = ?");
        $stmt->execute([$livre_id]);

        // 2. Supprimer les réservations liées
        $stmt = $pdo->prepare("DELETE FROM reservations WHERE livre_id = ?");
        $stmt->execute([$livre_id]);

        // 3. Supprimer le livre
        $stmt = $pdo->prepare("DELETE FROM livres WHERE id = ?");
        $stmt->execute([$livre_id]);

        $pdo->commit();
        header('Location: liste_livres.php?success=livre_supprime');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

?>

<div class="container mt-5">
    <h2>Confirmer la suppression du livre</h2>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <h4 class="card-title">Livre : <?= htmlspecialchars($livre['titre']) ?></h4>
            <p class="text-danger fw-bold">Attention : cette action est irréversible !</p>
            <p>Tous les exemplaires et réservations associées seront également supprimés.</p>
            
            <form method="POST">
                <button type="submit" class="btn btn-danger btn-lg">Oui, supprimer définitivement</button>
                <a href="liste_livres.php" class="btn btn-secondary btn-lg">Annuler</a>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>