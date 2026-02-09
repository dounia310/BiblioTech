<?php

// 1. CONNEXION DATABASE
function connectDB() {
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=bibliotech;charset=utf8mb4",
            "root",   
            "",         
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Erreur BD : " . $e->getMessage());
    }
}

$pdo = connectDB();

// 4. LOGOUT
function logout() {
    session_destroy();
    header('Location: index.php');
    exit;
}

// 5. CHECK LOGIN
function isLogged() {
    return isset($_SESSION['user_id']);
}

// 6. REDIRECT IF NOT LOGGED
function requireLogin() {
    if (!isLogged()) {
        header('Location: login.php');
        exit;
    }
}

// 7. HELPERS (auth/session conventions used by login.php)
function requireStaff(array $roles = []) {
    requireLogin();
    if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'staff') {
        header('Location: login.php');
        exit;
    }
    if (!empty($roles)) {
        $role = $_SESSION['role'] ?? null;
        if ($role === null || !in_array($role, $roles, true)) {
            header('Location: index.php');
            exit;
        }
    }
}

function requireMembre() {
    requireLogin();
    if (!isset($_SESSION['type']) || $_SESSION['type'] !== 'membre') {
        header('Location: login.php');
        exit;
    }
}
?>