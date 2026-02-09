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
    $stmt = $pdo->prepare("
        SELECT r.*, l.titre
        FROM reservations r
        JOIN livres l ON r.livre_id = l.id
        WHERE r.id = ?
        LIMIT 1
    ");
    $stmt->execute([$reservation_id]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        header('Location: dashboard.php');
        exit;
    }

    // Autorisations:
    // - membre: seulement sa réservation
    // - staff: bibliothecaire/admin/super_admin
    $is_staff = ($type === 'staff' && in_array($role, ['bibliothecaire','admin','super_admin'], true));
    $is_owner = ($type === 'membre' && (int)$reservation['membre_id'] === $user_id);

    if (!$is_staff && !$is_owner) {
        header('Location: dashboard.php');
        exit;
    }

    if (($reservation['statut'] ?? '') !== 'disponible') {
        header('Location: dashboard.php');
        exit;
    }

    // Marquer "récupéré"
    $upd = $pdo->prepare("UPDATE reservations SET statut = 'recupere', updated_at = NOW() WHERE id = ?");
    $upd->execute([$reservation_id]);

    // Audit
    $log = $pdo->prepare("
        INSERT INTO logs_audit (table_name, record_id, action, user_id, user_type)
        VALUES ('reservations', ?, 'UPDATE', ?, ?)
    ");
    $log->execute([$reservation_id, $user_id, $is_staff ? 'staff' : 'membre']);

} catch (PDOException $e) {
    // silence
}

if ($type === 'membre') {
    header('Location: mes_reservations.php');
    exit;
}

header('Location: dashboard.php');
exit;

