<?php
session_start();
require_once 'connexion.php';
require_once 'header.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['type'] ?? null) !== 'membre') {
    header('Location: login.php');
    exit;
}

$membre_id = (int)$_SESSION['user_id'];
$amende_id = (int)($_GET['id'] ?? 0);

if ($amende_id <= 0) {
    header('Location: mes_amendes.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM amendes WHERE id = ? AND membre_id = ? LIMIT 1");
$stmt->execute([$amende_id, $membre_id]);
$amende = $stmt->fetch();

if (!$amende) {
    echo '<div class="error">Amende introuvable.</div>';
    require_once 'footer.php';
    exit;
}

$montant = (float)$amende['montant'];
$paye = (float)$amende['montant_paye'];
$reste = max(0, $montant - $paye);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    if ($amount <= 0 || $amount > $reste) {
        $error = "Montant invalide (max " . number_format($reste, 2) . " €).";
    } else {
        try {
            $nouveau_paye = $paye + $amount;
            $nouveau_statut = ($nouveau_paye >= $montant) ? 'paye' : 'partiel';

            $upd = $pdo->prepare("
                UPDATE amendes
                SET montant_paye = ?,
                    statut = ?,
                    date_paiement = CASE WHEN ? = 'paye' THEN CURDATE() ELSE date_paiement END,
                    methode_paiement = 'en_ligne',
                    updated_at = NOW()
                WHERE id = ? AND membre_id = ?
            ");
            $upd->execute([$nouveau_paye, $nouveau_statut, $nouveau_statut, $amende_id, $membre_id]);

            $log = $pdo->prepare("
                INSERT INTO logs_audit (table_name, record_id, action, user_id, user_type)
                VALUES ('amendes', ?, 'UPDATE', ?, 'membre')
            ");
            $log->execute([$amende_id, $membre_id]);

            header('Location: mes_amendes.php');
            exit;
        } catch (PDOException $e) {
            $error = "Erreur lors du paiement.";
        }
    }
}
?>

<h1>Payer une amende</h1>

<?php if ($message): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>
<?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<p><strong>Montant:</strong> <?= number_format($montant, 2) ?> €</p>
<p><strong>Déjà payé:</strong> <?= number_format($paye, 2) ?> €</p>
<p><strong>Reste:</strong> <?= number_format($reste, 2) ?> €</p>

<?php if ($reste <= 0): ?>
    <p>Cette amende est déjà réglée.</p>
    <p><a href="mes_amendes.php">← Retour</a></p>
<?php else: ?>
    <form method="POST">
        <label for="amount">Montant à payer (max <?= number_format($reste, 2) ?> €)</label><br>
        <input type="number" step="0.01" min="0.01" max="<?= htmlspecialchars((string)$reste) ?>" name="amount" id="amount" value="<?= htmlspecialchars((string)$reste) ?>" required>
        <button type="submit">Payer</button>
    </form>
    <p style="margin-top:20px;"><a href="mes_amendes.php">← Retour</a></p>
<?php endif; ?>

<?php require_once 'footer.php'; ?>

