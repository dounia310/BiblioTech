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
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Erreur BD : " . $e->getMessage());
    }
}

$pdo = connectDB();

// 2. LOGOUT
function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}

// 3. CHECK LOGIN
function isLogged() {
    return isset($_SESSION['user_id']);
}

// 4. REDIRECT IF NOT LOGGED
function requireLogin() {
    if (!isLogged()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
}

// 5. REQUIRE STAFF - CORRIGÉ pour utiliser user_type
function requireStaff(array $roles = []) {
    requireLogin();
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'staff') {
        $_SESSION['error_message'] = "Accès réservé au personnel";
        header('Location: index.php');
        exit;
    }
    if (!empty($roles)) {
        $role = $_SESSION['role'] ?? null;
        if ($role === null || !in_array($role, $roles, true)) {
            $_SESSION['error_message'] = "Permissions insuffisantes";
            header('Location: index.php');
            exit;
        }
    }
}

// 6. REQUIRE MEMBRE - CORRIGÉ pour utiliser user_type
function requireMembre() {
    requireLogin();
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'membre') {
        $_SESSION['error_message'] = "Accès réservé aux membres";
        header('Location: index.php');
        exit;
    }
}

// 7. DEBUG FUNCTION
function debugLog($data, $label = 'DEBUG') {
    if (isset($_SESSION['debug_mode']) && $_SESSION['debug_mode'] === true) {
        error_log("[$label] " . print_r($data, true));
    }
}

// 8. SET MESSAGES
function setFlashMessage($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

// 9. GET FLASH MESSAGE
function getFlashMessage($type) {
    if (isset($_SESSION['flash_' . $type])) {
        $message = $_SESSION['flash_' . $type];
        unset($_SESSION['flash_' . $type]);
        return $message;
    }
    return null;
}

// 10. CHECK PERMISSION
function hasPermission($requiredRole) {
    if (!isset($_SESSION['user_type'])) return false;
    
    if ($_SESSION['user_type'] === 'staff') {
        $userRole = $_SESSION['role'] ?? null;
        return $userRole === $requiredRole;
    }
    
    return false;
}
?>