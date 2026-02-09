<?php
// supprimer_exemplaire.php
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['user_id']) || $_SESSION['type'] !== 'staff') {
    header('Location: login.php');
    exit;
}

$exemplaire_id = $_GET['id'] ?? 0;

if ($exemplaire_id) {
    // Récupérer le livre_id avant suppression pour la redirection
    $stmt = $pdo->prepare("SELECT livre_id FROM exemplaires WHERE id = ?");
    $stmt->execute([$exemplaire_id]);
    $exemplaire = $stmt->fetch();
    
    if ($exemplaire) {
        // Supprimer l'exemplaire
        $delete_stmt = $pdo->prepare("DELETE FROM exemplaires WHERE id = ?");
        $delete_stmt->execute([$exemplaire_id]);
        
        // Journaliser
        $log_stmt = $pdo->prepare("
            INSERT INTO logs_audit (table_name, record_id, action, user_id, user_type)
            VALUES ('exemplaires', ?, 'DELETE', ?, 'staff')
        ");
        $log_stmt->execute([$exemplaire_id, $_SESSION['user_id']]);
        
        header('Location: details_livre.php?id=' . $exemplaire['livre_id'] . '&success=Exemplaire supprimé');
        exit;
    }
}

header('Location: liste_livres.php?error=Exemplaire non trouvé');
exit;
?>