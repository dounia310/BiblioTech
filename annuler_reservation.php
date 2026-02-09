<?php
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$reservation_id = (int)($_GET['id'] ?? 0);
if ($reservation_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

$type = $_SESSION['type'] ?? null;
$role = $_SESSION['role'] ?? null;
$user_id = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ? LIMIT 1");
    $stmt->execute([$reservation_id]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        header('Location: dashboard.php');
        exit;
    }

    $is_staff = ($type === 'staff' && in_array($role, ['admin','super_admin','bibliothecaire','assistant'], true));
    $is_owner = ($type === 'membre' && (int)$reservation['membre_id'] === $user_id);

    if (!$is_staff && !$is_owner) {
        header('Location: dashboard.php');
        exit;
    }

    // Annulation seulement si active
    if (!in_array($reservation['statut'], ['en_attente', 'disponible'], true)) {
        header('Location: dashboard.php');
        exit;
    }

    $upd = $pdo->prepare("UPDATE reservations SET statut = 'annule', updated_at = NOW() WHERE id = ?");
    $upd->execute([$reservation_id]);

    if ($is_staff) {
        $log = $pdo->prepare("
            INSERT INTO logs_audit (table_name, record_id, action, user_id, user_type)
            VALUES ('reservations', ?, 'UPDATE', ?, 'staff')
        ");
        $log->execute([$reservation_id, $user_id]);
    } else {
        $log = $pdo->prepare("
            INSERT INTO logs_audit (table_name, record_id, action, user_id, user_type)
            VALUES ('reservations', ?, 'UPDATE', ?, 'membre')
        ");
        $log->execute([$reservation_id, $user_id]);
    }

} catch (PDOException $e) {
    // silence (version simple)
}

if ($type === 'membre') {
    header('Location: mes_reservations.php');
    exit;
}

// staff: retour à la fiche membre si possible, sinon dashboard
header('Location: dashboard.php');
exit;

