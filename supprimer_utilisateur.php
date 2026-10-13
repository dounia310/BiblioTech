<?php
require_once 'connexion.php';
session_start();

// Protection : uniquement admin ou super_admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    header('Location: login.php');
    exit;
}

$user_id_to_delete = (int)($_GET['id'] ?? 0);
$current_user_id = (int)$_SESSION['user_id'];

if ($user_id_to_delete <= 0 || $user_id_to_delete === $current_user_id) {
    header('Location: liste_utilisateurs.php?message=' . urlencode('Action non autorisée ou utilisateur invalide'));
    exit;
}

try {
    // Vérifier que l'utilisateur existe
    $check = $pdo->prepare("SELECT id FROM utilisateurs WHERE id = ?");
    $check->execute([$user_id_to_delete]);
    if (!$check->fetch()) {
        header('Location: liste_utilisateurs.php?message=' . urlencode('Utilisateur introuvable'));
        exit;
    }

    // Suppression
    $delete = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
    $delete->execute([$user_id_to_delete]);

    $message = "Utilisateur supprimé avec succès.";
    header('Location: liste_utilisateurs.php?message=' . urlencode($message));
    exit;

} catch (PDOException $e) {
    $error = "Erreur lors de la suppression : " . $e->getMessage();
    header('Location: liste_utilisateurs.php?message=' . urlencode($error));
    exit;
}