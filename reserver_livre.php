<?php
// reserver_livre.php     ← nom plus clair et cohérent avec tes autres fichiers
define('DEBUG', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'connexion.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    $pdo = connectDB(); // ou ta fonction de connexion
}

if (DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Auth membre
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || 
    !in_array($_SESSION['role'] ?? $_SESSION['type'] ?? '', ['membre', 'member'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$membre_id   = (int)$_SESSION['user_id'];
$membre_nom  = $_SESSION['prenom'] . ' ' . $_SESSION['nom'] ?? 'Membre';

$csrf_name = 'csrf_reservation';
if (!isset($_SESSION[$csrf_name])) {
    $_SESSION[$csrf_name] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION[$csrf_name];

$livre       = null;
$error       = '';
$success     = '';
$exemplaire_reserve = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['livre_id'])) {

        $livre_id = (int)$_POST['livre_id'];
        if ($livre_id < 1) {
            $error = "Identifiant de livre invalide.";
        } else {
            // Charger le livre
            $stmt = $pdo->prepare("SELECT id, titre, auteur, couverture_url FROM livres WHERE id = ?");
            $stmt->execute([$livre_id]);
            $livre = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$livre) {
                $error = "Livre introuvable.";
            }
        }

        if (!$error && isset($_POST['confirmer_reservation'])) {

            if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
                $error = "Vérification de sécurité échouée. Veuillez réessayer.";
            } else {
                $pdo->beginTransaction();
                try {
                    // 1. Vérifier si déjà réservé par ce membre (statut actif)
                    $check = $pdo->prepare("
                        SELECT id FROM reservations 
                        WHERE livre_id = ? AND membre_id = ? 
                        AND statut IN ('en_attente', 'disponible')
                        LIMIT 1
                    ");
                    $check->execute([$livre_id, $membre_id]);
                    if ($check->fetch()) {
                        throw new Exception("Vous avez déjà une réservation active pour ce livre.");
                    }

                    // 2. Trouver et verrouiller 1 exemplaire disponible
                    $stmt = $pdo->prepare("
                        SELECT id FROM exemplaires 
                        WHERE livre_id = ? AND statut = 'disponible' 
                        LIMIT 1 FOR UPDATE
                    ");
                    $stmt->execute([$livre_id]);
                    $ex = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$ex) {
                        throw new Exception("Aucun exemplaire disponible pour le moment.");
                    }

                    $exemplaire_id = (int)$ex['id'];

                    // 3. Marquer l'exemplaire comme réservé
                    $pdo->prepare("UPDATE exemplaires SET statut = 'reserve' WHERE id = ?")
                        ->execute([$exemplaire_id]);

                    // 4. Créer la réservation
                    $has_ex_col = $pdo->query("SHOW COLUMNS FROM reservations LIKE 'exemplaire_id'")->fetch();
                    $cols = $has_ex_col ? "livre_id, exemplaire_id, membre_id, date_reservation, date_expiration, statut"
                                        : "livre_id, membre_id, date_reservation, date_expiration, statut";
                    $vals = $has_ex_col ? "?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'en_attente'"
                                        : "?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'en_attente'";
                    $params = $has_ex_col ? [$livre_id, $exemplaire_id, $membre_id] : [$livre_id, $membre_id];

                    $pdo->prepare("INSERT INTO reservations ($cols) VALUES ($vals)")->execute($params);
                    $reservation_id = $pdo->lastInsertId();

                    // 5. Notification POUR LES ADMINS / BIBLIOTHECAIRES
                    $msg = "Nouvelle réservation : «{$livre['titre']}» par {$membre_nom} (#{$membre_id})";

                    // Option 1 : notification par ligne pour chaque admin (plus fiable)
                    $admins = $pdo->query("
                        SELECT id FROM utilisateurs 
                        WHERE role IN ('admin','bibliothecaire','super_admin')
                    ")->fetchAll(PDO::FETCH_COLUMN);

                    foreach ($admins as $admin_id) {
                        $pdo->prepare("
                            INSERT INTO notifications 
                            (user_id, user_type, type, titre, message, lien, lue, envoyee, created_at)
                            VALUES (?, 'staff', 'reservation', 'Nouvelle réservation', ?, ?, 0, 0, NOW())
                        ")->execute([
                            $admin_id,
                            $msg,
                            "gerer_reservations.php?view={$reservation_id}"   // ← adapte le lien
                        ]);
                    }

                    // Option 2 alternative (broadcast) : une seule ligne avec user_type = 'all_staff' ou NULL

                    $pdo->commit();

                    $success = "Réservation confirmée ! Vous avez 7 jours pour venir récupérer l'exemplaire.";
                    $exemplaire_reserve = $exemplaire_id;

                    // Régénérer CSRF
                    $_SESSION[$csrf_name] = bin2hex(random_bytes(32));
                    $csrf_token = $_SESSION[$csrf_name];

                    // Redirection recommandée après succès (PRG pattern)
                    header("Location: reserver_livre.php?livre_id={$livre_id}&success=1");
                    exit;

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = $e->getMessage();
                    error_log("Erreur réservation : " . $e->getMessage());
                }
            }
        }
    }
} catch (Exception $e) {
    $error = "Erreur inattendue : " . $e->getMessage();
    error_log($e);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réserver – Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #2b848e;
            --success: #198754;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #212529;
        }
        body { font-family: system-ui, sans-serif; background: var(--light); color: var(--dark); line-height: 1.6; margin:0; }
        .container { max-width: 1100px; margin: 2rem auto; padding: 0 1rem; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; }
        .hero { display: grid; grid-template-columns: 1fr 320px; gap: 2rem; padding: 2rem; }
        .hero h1 { margin: 0 0 0.75rem; font-size: 2.1rem; }
        .cover img { width: 100%; height: 420px; object-fit: cover; border-radius: 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); }
        .btn { padding: 0.9rem 1.8rem; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.6rem; text-decoration: none; }
        .btn-primary { background: var(--primary); color: white; border: none; }
        .btn-primary:hover { background: #236d75; }
        .btn-primary:disabled { background: #aaa; cursor: not-allowed; }
        .back-links { margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap; }
        @media (max-width: 900px) {
            .hero { grid-template-columns: 1fr; text-align: center; }
            .cover img { height: 340px; max-width: 280px; margin: 1rem auto; }
        }
    </style>
</head>
<body>

<div class="container">

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success']) || $success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($success ?: "Réservation confirmée ! Vous avez 7 jours pour récupérer le livre.") ?>
        </div>
    <?php endif; ?>

    <?php if (!$livre): ?>
        <div class="alert alert-danger">
            <i class="fas fa-book"></i> Aucun livre sélectionné ou introuvable.
        </div>
    <?php else: ?>

        <div class="card">
            <div class="hero">
                <div>
                    <h1><?= htmlspecialchars($livre['titre']) ?></h1>
                    <p><strong>Auteur :</strong> <?= htmlspecialchars($livre['auteur'] ?? 'Non renseigné') ?></p>

                    <?php if ($success || isset($_GET['success'])): ?>
                        <p style="margin: 1.5rem 0; font-size: 1.1rem;">
                            <i class="fas fa-info-circle"></i> Rendez-vous à la bibliothèque dans les 7 prochains jours.
                        </p>
                        <div class="back-links">
                            <a href="catalogue.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Retour catalogue</a>
                            <a href="mes_reservations.php" class="btn btn-primary"><i class="fas fa-list"></i> Mes réservations</a>
                        </div>
                    <?php else: ?>
                        <form method="POST" style="margin: 2rem 0;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="livre_id" value="<?= (int)$livre['id'] ?>">

                            <?php if (true): // tu peux remettre la vérif $exemplaires_ok si tu veux un pré-check ?>
                                <button type="submit" name="confirmer_reservation" class="btn btn-primary">
                                    <i class="fas fa-bookmark"></i> Confirmer la réservation
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-primary" disabled>
                                    <i class="fas fa-clock"></i> Temporairement indisponible
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="cover">
                    <?php if (!empty($livre['couverture_url'])): ?>
                        <img src="<?= htmlspecialchars($livre['couverture_url']) ?>" alt="Couverture de <?= htmlspecialchars($livre['titre']) ?>">
                    <?php else: ?>
                        <div style="height:420px; background:#e9ecef; display:flex; align-items:center; justify-content:center; font-size:120px; color:#adb5bd;">
                            <i class="fas fa-book"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php endif; ?>

    <div class="back-links">
        <a href="catalogue.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Catalogue</a>
        <a href="membre_dashboard.php" class="btn btn-primary"><i class="fas fa-home"></i> Mon espace</a>
    </div>

</div>

</body>
</html>