<?php
require_once 'connexion.php';
require_once 'header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT r.id, r.date_reservation, r.statut, r.position,
           m.prenom, m.nom, m.numero_membre,
           l.titre, l.auteur
    FROM reservations r
    JOIN membres m ON r.membre_id = m.id
    JOIN livres l ON r.livre_id = l.id
    ORDER BY r.date_reservation DESC
");
$stmt->execute();
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="dashboard-container">
    <h1>Gestion des Réservations</h1>

    <?php if (empty($reservations)): ?>
        <div class="alert alert-info">Aucune réservation trouvée.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Membre</th>
                        <th>Livre</th>
                        <th>Date réservation</th>
                        <th>Statut</th>
                        <th>Position</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['prenom'] . ' ' . $r['nom']) ?><br>
                                <small><?= $r['numero_membre'] ?></small></td>
                            <td><?= htmlspecialchars($r['titre']) ?><br>
                                <small><?= htmlspecialchars($r['auteur']) ?></small></td>
                            <td><?= date('d/m/Y H:i', strtotime($r['date_reservation'])) ?></td>
                            <td>
                                <span class="badge <?= $r['statut'] === 'disponible' ? 'bg-success' : 'bg-warning' ?>">
                                    <?= ucfirst($r['statut']) ?>
                                </span>
                            </td>
                            <td><?= $r['position'] ? '#' . $r['position'] : '—' ?></td>
                            <td>
                                <?php if ($r['statut'] === 'disponible'): ?>
                                    <a href="recuperer_reservation.php?id=<?= $r['id'] ?>&admin=1" class="btn btn-sm btn-success">Marquer récupéré</a>
                                <?php endif; ?>
                                <a href="annuler_reservation.php?id=<?= $r['id'] ?>&admin=1" class="btn btn-sm btn-danger" onclick="return confirm('Confirmer l\'annulation ?');">Annuler</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'footer.php'; ?>