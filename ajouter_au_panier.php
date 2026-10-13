<?php
session_start();
require_once 'connexion.php';

// Vérifier si l'ID du livre est passé
if (isset($_POST['livre_id']) || isset($_GET['livre_id'])) {
    $livre_id = isset($_POST['livre_id']) ? $_POST['livre_id'] : $_GET['livre_id'];
    $quantite = isset($_POST['quantite']) ? intval($_POST['quantite']) : 1;
    
    // Récupérer les informations du livre depuis la base de données
    try {
        $sql = "SELECT l.*, a.nom as auteur_nom, a.prenom as auteur_prenom 
                FROM livres l 
                LEFT JOIN auteurs a ON l.auteur_id = a.id 
                WHERE l.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$livre_id]);
        $livre = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($livre) {
            // Initialiser le panier s'il n'existe pas
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            
            // Vérifier si le livre est déjà dans le panier
            $found = false;
            foreach ($_SESSION['cart'] as &$item) {
                if ($item['id'] == $livre_id) {
                    // Augmenter la quantité
                    $item['quantity'] += $quantite;
                    $item['total'] = $item['quantity'] * $item['prix'];
                    $found = true;
                    break;
                }
            }
            
            // Si le livre n'est pas encore dans le panier
            if (!$found) {
                $item = [
                    'id' => $livre['id'],
                    'titre' => $livre['titre'],
                    'auteur' => $livre['auteur_nom'] . ' ' . $livre['auteur_prenom'],
                    'prix' => $livre['prix'] ?? 0,
                    'image' => $livre['image'] ?? 'default.jpg',
                    'quantity' => $quantite,
                    'total' => ($livre['prix'] ?? 0) * $quantite
                ];
                $_SESSION['cart'][] = $item;
            }
            
            // Calculer le nombre total d'articles dans le panier
            $totalItems = 0;
            foreach ($_SESSION['cart'] as $item) {
                $totalItems += $item['quantity'];
            }
            
            // Stocker le total dans la session
            $_SESSION['cart_total_items'] = $totalItems;
            
            // Message de succès
            $_SESSION['success_message'] = "Le livre a été ajouté au panier !";
            
            // Rediriger vers la page précédente ou le panier
            $redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'catalogue.php';
            header("Location: $redirect_url");
            exit();
            
        } else {
            $_SESSION['error_message'] = "Livre non trouvé !";
            header("Location: catalogue.php");
            exit();
        }
        
    } catch (PDOException $e) {
        error_log("Erreur PDO: " . $e->getMessage());
        $_SESSION['error_message'] = "Une erreur est survenue lors de l'ajout au panier.";
        header("Location: catalogue.php");
        exit();
    }
    
} else {
    $_SESSION['error_message'] = "ID du livre manquant !";
    header("Location: catalogue.php");
    exit();
}
?>