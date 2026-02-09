<?php
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$emprunt_id = (int)($_GET['id'] ?? 0);
if ($emprunt_id <= 0) {
    header('Location: dashboard.php');
    exit;
}

try {
    // Charger l'emprunt + contexte (membre/livre) et vérifier permissions
    $stmt = $pdo->prepare("
        SELECT e.*,
               ex.livre_id,
               l.titre,
               m.id as membre_id
        FROM emprunts e
        JOIN exemplaires ex ON e.exemplaire_id = ex.id
        JOIN livres l ON ex.livre_id = l.id
        JOIN membres m ON e.membre_id = m.id
        WHERE e.id = ?
        LIMIT 1
    ");
    $stmt->execute([$emprunt_id]);
    $emprunt = $stmt->fetch();

    if (!$emprunt) {
        header('Location: dashboard.php');
        exit;
    }

    $type = $_SESSION['type'] ?? null;
    $role = $_SESSION['role'] ?? null;
    $user_id = (int)$_SESSION['user_id'];

    $is_staff = ($type === 'staff');
    $is_membre_owner = ($type === 'membre' && (int)$emprunt['membre_id'] === $user_id);

    if (!$is_staff && !$is_membre_owner) {
        header('Location: dashboard.php');
        exit;
    }

    // Doit être en cours
    if (($emprunt['statut'] ?? '') !== 'en_cours') {
        header('Location: dashboard.php');
        exit;
    }

    // Paramètres prolongation (fallback)
    $prolongation_max = (int)($pdo->query("
        SELECT valeur FROM parametres
        WHERE categorie='emprunt' AND cle='prolongation_max'
        LIMIT 1
    ")->fetchColumn() ?? 2);

    $prolongation_duree = (int)($pdo->query("
        SELECT valeur FROM parametres
        WHERE categorie='emprunt' AND cle='prolongation_duree'
        LIMIT 1
    ")->fetchColumn() ?? 7);

    if ($prolongation_max <= 0) $prolongation_max = 2;
    if ($prolongation_duree <= 0) $prolongation_duree = 7;

    // Vérifier limite de prolongations
    if ((int)$emprunt['prolongations'] >= $prolongation_max) {
        header('Location: dashboard.php');
        exit;
    }

    // Si réservations en attente sur ce livre -> bloquer
    $stmt_res = $pdo->prepare("
        SELECT COUNT(*)
        FROM reservations
        WHERE livre_id = ? AND statut = 'en_attente'
    ");
    $stmt_res->execute([(int)$emprunt['livre_id']]);
    $nb_res = (int)$stmt_res->fetchColumn();
    if ($nb_res > 0) {
        header('Location: dashboard.php');
        exit;
    }

    // Appliquer prolongation
    $stmt_upd = $pdo->prepare("
        UPDATE emprunts
        SET prolongations = prolongations + 1,
            date_retour_prevue = DATE_ADD(date_retour_prevue, INTERVAL ? DAY)
        WHERE id = ?
    ");
    $stmt_upd->execute([$prolongation_duree, $emprunt_id]);

    // Audit (optionnel)
    if ($is_staff) {
        $stmt_log = $pdo->prepare("
            INSERT INTO logs_audit (table_name, record_id, action, user_id, user_type)
            VALUES ('emprunts', ?, 'UPDATE', ?, 'staff')
        ");
        $stmt_log->execute([$emprunt_id, $user_id]);
    }

} catch (PDOException $e) {
    // En cas d'erreur, ne pas exposer SQL
}

// Retour vers la page logique
if (($_SESSION['type'] ?? null) === 'membre') {
    header('Location: membre_dashboard.php');
    exit;
}

header('Location: emprunts.php');
exit;

