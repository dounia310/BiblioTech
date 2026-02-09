<?php
session_start();

// Redirection simple selon le type d'utilisateur
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$type = $_SESSION['type'] ?? null;
$role = $_SESSION['role'] ?? null;

if ($type === 'membre') {
    header('Location: membre_dashboard.php');
    exit;
}

if ($type === 'staff') {
    if ($role === 'admin' || $role === 'super_admin') {
        header('Location: admin_dashboard.php');
        exit;
    }
    // Tableau de bord staff "générique"
    header('Location: emprunts.php');
    exit;
}

header('Location: index.php');
exit;