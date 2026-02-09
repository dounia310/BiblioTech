<?php
session_start();
require_once 'connexion.php';

// Vérifier l'authentification (staff seulement)
if (!isset($_SESSION['user_id']) || $_SESSION['type'] !== 'staff') {
    header('Location: login.php');
    exit;
}
// Récupérer les emprunts - VERSION COMPLÈTEMENT CORRIGÉE
try {
    $emprunts = $pdo->query("
        SELECT 
            e.*, 
            m.id as membre_id,
            m.numero_membre,
            CONCAT(m.nom, ' ', m.prenom) as membre_nom,
            m.email as membre_email,
            l.titre,
            l.isbn,
            a.nom_complet as auteur,  -- CHANGÉ ICI : a.nom_complet au lieu de l.auteur
            ex.code_barre,
            ex.code_inventaire,
            DATEDIFF(CURDATE(), e.date_retour_prevue) as jours_retard
        FROM emprunts e
        JOIN membres m ON e.membre_id = m.id
        JOIN exemplaires ex ON e.exemplaire_id = ex.id
        JOIN livres l ON ex.livre_id = l.id
        JOIN auteurs a ON l.auteur_id = a.id  -- AJOUTÉ ICI
        WHERE e.statut = 'en_cours'
        ORDER BY e.date_retour_prevue ASC
    ")->fetchAll();
    
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

// Filtrer si un paramètre est passé
$filter = $_GET['filter'] ?? '';
if ($filter === 'retard') {
    $emprunts = array_filter($emprunts, function($e) {
        return $e['jours_retard'] > 0;
    });
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emprunts en cours - Bibliotech</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .stats { display: flex; gap: 15px; margin-bottom: 20px; }
        .stat-card { flex: 1; background: white; padding: 15px; border-radius: 8px; text-align: center; }
        .table { width: 100%; background: white; border-collapse: collapse; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .table th { background: #007bff; color: white; }
        .retard { color: #dc3545; font-weight: bold; }
        .btn { padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .btn-danger { background: #dc3545; }
        .btn-warning { background: #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 Emprunts en cours</h1>
            <p>Gestion des prêts actuels</p>
            
            <div class="stats">
                <a href="emprunts.php" class="stat-card">Tous (<?= count($emprunts) ?>)</a>
                <a href="emprunts.php?filter=retard" class="stat-card">En retard (<?= count(array_filter($emprunts, fn($e) => $e['jours_retard'] > 0)) ?>)</a>
                <a href="nouvel_emprunt.php" class="stat-card">Nouvel emprunt</a>
                <a href="dashboard.php" class="stat-card">← Retour</a>
            </div>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Membre</th>
                    <th>Livre</th>
                    <th>Emprunt</th>
                    <th>Retour prévu</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($emprunts as $emprunt): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($emprunt['membre_nom']) ?></strong><br>
                        <small><?= htmlspecialchars($emprunt['membre_email']) ?></small>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($emprunt['titre']) ?></strong><br>
                        <small><?= htmlspecialchars($emprunt['auteur']) ?></small>
                    </td>
                    <td><?= date('d/m/Y', strtotime($emprunt['date_emprunt'])) ?></td>
                    <td class="<?= $emprunt['jours_retard'] > 0 ? 'retard' : '' ?>">
                        <?= date('d/m/Y', strtotime($emprunt['date_retour_prevue'])) ?>
                        <?php if ($emprunt['jours_retard'] > 0): ?>
                            <br><small>(+<?= $emprunt['jours_retard'] ?> jours)</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($emprunt['jours_retard'] > 0): ?>
                            <span class="badge badge-danger">En retard</span>
                        <?php else: ?>
                            <span class="badge badge-success">En cours</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button onclick="retourner(<?= $emprunt['id'] ?>)" class="btn">Retour</button>
                        <button onclick="prolonger(<?= $emprunt['id'] ?>)" class="btn btn-warning">Prolonger</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <script>
    function retourner(id) {
        if (confirm('Marquer ce livre comme retourné ?')) {
            window.location.href = 'retour_livre.php?id=' + id;
        }
    }
    
    function prolonger(id) {
        if (confirm('Prolonger cet emprunt de 7 jours ?')) {
            window.location.href = 'prolonger_emprunt.php?id=' + id;
        }
    }
    </script>
</body>
</html>