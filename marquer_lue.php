<?php
require_once 'connexion.php';
session_start();

if (!isset($_SESSION['user_id'])) exit;

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE notifications SET lue = 1 WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
    $stmt->execute([$id, $_SESSION['user_id']]);
}