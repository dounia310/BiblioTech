<?php
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $livre_id = (int)($_POST['livre_id'] ?? 0);
    $type     = $_POST['type'] ?? 'achat';
    $quantite = max(1, (int)($_POST['quantite'] ?? 1));

    if ($livre_id <= 0) {
        $_SESSION['error_message'] = "Livre invalide.";
        header('Location: details_livre.php?id=' . $livre_id);
        exit;
    }

    try {
        // Vérifier si déjà dans le panier
        $stmt = $pdo->prepare("
            SELECT id, quantite 
            FROM panier_items 
            WHERE user_id = ? AND livre_id = ? AND type = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $livre_id, $type]);
        $existant = $stmt->fetch();

        if ($existant) {
            // Mise à jour quantité
            $nouvelle_quantite = $existant['quantite'] + $quantite;
            $stmt = $pdo->prepare("
                UPDATE panier_items 
                SET quantite = ?, date_ajout = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$nouvelle_quantite, $existant['id']]);
        } else {
            // Nouvel ajout
            $stmt = $pdo->prepare("
                INSERT INTO panier_items 
                (user_id, livre_id, type, quantite, date_ajout)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $livre_id, $type, $quantite]);
        }

        $_SESSION['success_message'] = "Article ajouté au panier !";
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur : " . $e->getMessage();
    }
}

header('Location: panier.php');
exit;