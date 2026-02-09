<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? 0;
$quantity = $_POST['quantity'] ?? 1;

switch ($action) {
    case 'add':
        if (!isset($_SESSION['panier'][$id])) {
            $_SESSION['panier'][$id] = ['quantite' => $quantity];
        } else {
            $_SESSION['panier'][$id]['quantite'] += $quantity;
        }
        echo json_encode(['success' => true, 'message' => 'Livre ajouté au panier']);
        break;
        
    case 'update':
        if (isset($_SESSION['panier'][$id])) {
            $_SESSION['panier'][$id]['quantite'] = $quantity;
        }
        echo json_encode(['success' => true, 'message' => 'Quantité mise à jour']);
        break;
        
    case 'remove':
        if (isset($_SESSION['panier'][$id])) {
            unset($_SESSION['panier'][$id]);
        }
        echo json_encode(['success' => true, 'message' => 'Livre retiré du panier']);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
}