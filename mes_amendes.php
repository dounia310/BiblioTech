<?php
session_start();
require_once 'connexion.php';
require_once 'header.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['type'] ?? null) !== 'membre') {
    header('Location: login.php');
    exit;
}

$membre_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT a.*,
           l.titre
    FROM amendes a
    LEFT JOIN emprunts e ON a.emprunt_id = e.id
    LEFT JOIN exemplaires ex ON e.exemplaire_id = ex.id
    LEFT JOIN livres l ON ex.livre_id = l.id
    WHERE a.membre_id = ?
    ORDER BY a.date_creation DESC
");
$stmt->execute([$membre_id]);
$amendes = $stmt->fetchAll();

$total_restant = 0.0;
foreach ($amendes as $a) {
    $total_restant += max(0, (float)$a['montant'] - (float)$a['montant_paye']);
}
?>

<h1>Mes amendes</h1>
<p><strong>Total restant à payer :</strong> <?= number_format($total_restant, 2) ?> €</p>

<?php if (empty($amendes)): ?>
    <p>Aucune amende.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Livre</th>
                <th>Montant</th>
                <th>Payé</th>
                <th>Reste</th>
                <th>Statut</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($amendes as $a): ?>
            <?php
                $montant = (float)$a['montant'];
                $paye = (float)$a['montant_paye'];
                $reste = max(0, $montant - $paye);
            ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($a['date_creation'])) ?></td>
                <td><?= htmlspecialchars($a['type']) ?></td>
                <td><?= htmlspecialchars($a['titre'] ?? '-') ?></td>
                <td><?= number_format($montant, 2) ?> €</td>
                <td><?= number_format($paye, 2) ?> €</td>
                <td><strong><?= number_format($reste, 2) ?> €</strong></td>
                <td><?= htmlspecialchars($a['statut']) ?></td>
                <td>
                    <?php if ($reste > 0 && in_array($a['statut'], ['impaye','partiel'], true)): ?>
                        <a href="payer_amende.php?id=<?= (int)$a['id'] ?>">Payer</a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p style="margin-top:20px;"><a href="membre_dashboard.php">← Retour à mon espace</a></p>

<?php require_once 'footer.php'; ?>

