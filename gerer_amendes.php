<?php
require_once 'connexion.php';
require_once 'header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT a.id, a.montant, a.montant_paye, a.statut, a.date_creation, a.description,
           m.prenom, m.nom, m.numero_membre,
           l.titre
    FROM amendes a
    JOIN membres m ON a.membre_id = m.id
    LEFT JOIN emprunts e ON a.emprunt_id = e.id
    LEFT JOIN exemplaires ex ON e.exemplaire_id = ex.id
    LEFT JOIN livres l ON ex.livre_id = l.id
    ORDER BY a.date_creation DESC
");
$stmt->execute();
$amendes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="dashboard-container">
    <h1>Gestion des Amendes</h1>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Membre</th>
                    <th>Motif</th>
                    <th>Montant</th>
                    <th>Payé</th>
                    <th>Reste</th>
                    <th>Date</th>
                    <th>Statut</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($amendes as $a):
                    $reste = $a['montant'] - $a['montant_paye'];
                ?>
                <tr>
                    <td><?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?><br><small><?= $a['numero_membre'] ?></small></td>
                    <td><?= htmlspecialchars($a['description'] ?: 'Retard / Dommage') ?></td>
                    <td><?= number_format($a['montant'], 2) ?> DH</td>
                    <td><?= number_format($a['montant_paye'], 2) ?> DH</td>
                    <td class="<?= $reste > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= number_format($reste, 2) ?> DH
                    </td>
                    <td><?= date('d/m/Y', strtotime($a['date_creation'])) ?></td>
                    <td>
                        <span class="badge <?= $a['statut'] === 'payee' ? 'bg-success' : 'bg-warning' ?>">
                            <?= ucfirst($a['statut']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($reste > 0): ?>
                            <a href="payer_amende.php?id=<?= $a['id'] ?>&admin=1" class="btn btn-sm btn-primary">Enregistrer paiement</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'footer.php'; ?>