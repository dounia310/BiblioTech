<?php
session_start();
require_once 'connexion.php';
require_once 'header.php';
// Vérifier si admin
if(!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin')) {
    header("Location: index.php");
    exit();
}

// Dates par défaut (mois en cours)
$debut = $_GET['debut'] ?? date('Y-m-01');
$fin = $_GET['fin'] ?? date('Y-m-t');

// Statistiques générales
$stats = [];
$stats['livres'] = $pdo->query("SELECT COUNT(*) FROM livres")->fetchColumn();
$stats['membres'] = $pdo->query("SELECT COUNT(*) FROM membres")->fetchColumn();
$stats['emprunts_total'] = $pdo->query("SELECT COUNT(*) FROM emprunts")->fetchColumn();
$stats['emprunts_mois'] = $pdo->query("SELECT COUNT(*) FROM emprunts WHERE date_emprunt BETWEEN '$debut' AND '$fin'")->fetchColumn();

// Emprunts par type de membre ce mois-ci
try {
    $emprunts_par_type = $pdo->query("
        SELECT m.type_membre, COUNT(e.id) as nombre
        FROM emprunts e
        JOIN membres m ON e.membre_id = m.id
        WHERE e.date_emprunt BETWEEN '$debut' AND '$fin'
        GROUP BY m.type_membre
    ")->fetchAll();
} catch (Exception $e) {
    $emprunts_par_type = [];
    echo "<!-- Erreur emprunts par type: " . $e->getMessage() . " -->";
}

// Livres les plus empruntés ce mois-ci
try {
    $livres_populaires = $pdo->query("
        SELECT l.titre, COUNT(e.id) as nb_emprunts
        FROM emprunts e
        JOIN exemplaires ex ON e.exemplaire_id = ex.id
        JOIN livres l ON ex.livre_id = l.id
        WHERE e.date_emprunt BETWEEN '$debut' AND '$fin'
        GROUP BY l.id
        ORDER BY nb_emprunts DESC
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $livres_populaires = [];
    echo "<!-- Erreur livres populaires: " . $e->getMessage() . " -->";
}

// Membres les plus actifs ce mois-ci
try {
    $membres_actifs = $pdo->query("
        SELECT m.nom, m.prenom, COUNT(e.id) as nb_emprunts
        FROM emprunts e
        JOIN membres m ON e.membre_id = m.id
        WHERE e.date_emprunt BETWEEN '$debut' AND '$fin'
        GROUP BY e.membre_id
        ORDER BY nb_emprunts DESC
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $membres_actifs = [];
    echo "<!-- Erreur membres actifs: " . $e->getMessage() . " -->";
}

// Récupérer les paramètres système
$parametres = [];
try {
    $stmt = $pdo->query("SELECT cle, valeur FROM parametres WHERE categorie IN ('emprunt', 'amende', 'membre')");
    $parametres = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    echo "<!-- Erreur paramètres: " . $e->getMessage() . " -->";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Rapports et Statistiques</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2, h3 { color: #333; }
        .period-form { background: #f5f5f5; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .period-form input { padding: 5px; margin: 0 10px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: white; border: 1px solid #ddd; border-radius: 5px; padding: 15px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card h3 { margin-top: 0; color: #666; font-size: 14px; }
        .stat-card .number { font-size: 24px; font-weight: bold; color: #007bff; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .export-links { margin: 20px 0; }
        .export-links a { display: inline-block; margin-right: 10px; padding: 8px 15px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; }
        .export-links a:hover { background: #218838; }
        .error { color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; padding: 10px; background: #fff3cd; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>📊 Rapports et Statistiques</h1>
    
    <div class="period-form">
        <h2>Période d'analyse</h2>
        <form method="GET">
            <label for="debut">Du :</label>
            <input type="date" id="debut" name="debut" value="<?php echo $debut; ?>" required>
            
            <label for="fin">Au :</label>
            <input type="date" id="fin" name="fin" value="<?php echo $fin; ?>" required>
            
            <button type="submit">Actualiser</button>
        </form>
        <p style="margin-top: 10px; color: #666;">
            Période : <?php echo date('d/m/Y', strtotime($debut)); ?> au <?php echo date('d/m/Y', strtotime($fin)); ?>
        </p>
    </div>
    
    <h2>Statistiques générales</h2>
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Livres dans la bibliothèque</h3>
            <div class="number"><?php echo $stats['livres']; ?></div>
        </div>
        <div class="stat-card">
            <h3>Membres inscrits</h3>
            <div class="number"><?php echo $stats['membres']; ?></div>
        </div>
        <div class="stat-card">
            <h3>Emprunts (tous temps)</h3>
            <div class="number"><?php echo $stats['emprunts_total']; ?></div>
        </div>
        <div class="stat-card">
            <h3>Emprunts ce mois</h3>
            <div class="number"><?php echo $stats['emprunts_mois']; ?></div>
        </div>
    </div>
    
    <h2>Emprunts par type de membre (<?php echo date('m/Y', strtotime($debut)); ?>)</h2>
    <?php if(count($emprunts_par_type) > 0): ?>
        <table>
            <tr>
                <th>Type de membre</th>
                <th>Nombre d'emprunts</th>
                <th>Pourcentage</th>
            </tr>
            <?php 
            $total_emprunts_type = array_sum(array_column($emprunts_par_type, 'nombre'));
            foreach($emprunts_par_type as $e): 
                $pourcentage = $total_emprunts_type > 0 ? round(($e['nombre'] / $total_emprunts_type) * 100, 1) : 0;
            ?>
            <tr>
                <td><?php echo ucfirst($e['type_membre']); ?></td>
                <td><?php echo $e['nombre']; ?></td>
                <td><?php echo $pourcentage; ?>%</td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p class="warning">Aucun emprunt enregistré pour cette période.</p>
    <?php endif; ?>
    
    <h2>Livres les plus populaires (top 10)</h2>
    <?php if(count($livres_populaires) > 0): ?>
        <table>
            <tr>
                <th>Livre</th>
                <th>Nombre d'emprunts</th>
            </tr>
            <?php foreach($livres_populaires as $l): ?>
            <tr>
                <td><?php echo htmlspecialchars($l['titre']); ?></td>
                <td><?php echo $l['nb_emprunts']; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p class="warning">Aucun livre emprunté pour cette période.</p>
    <?php endif; ?>
    
    <h2>Membres les plus actifs (top 10)</h2>
    <?php if(count($membres_actifs) > 0): ?>
        <table>
            <tr>
                <th>Membre</th>
                <th>Nombre d'emprunts</th>
            </tr>
            <?php foreach($membres_actifs as $m): ?>
            <tr>
                <td><?php echo htmlspecialchars($m['nom'] . ' ' . $m['prenom']); ?></td>
                <td><?php echo $m['nb_emprunts']; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p class="warning">Aucun emprunt pour cette période.</p>
    <?php endif; ?>
    
    <h2>Paramètres système</h2>
    <?php if(count($parametres) > 0): ?>
        <table>
            <tr>
                <th>Paramètre</th>
                <th>Valeur</th>
            </tr>
            <?php foreach($parametres as $cle => $valeur): ?>
            <tr>
                <td><?php echo htmlspecialchars($cle); ?></td>
                <td><?php echo htmlspecialchars($valeur); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p class="warning">Aucun paramètre trouvé.</p>
    <?php endif; ?>
    
    <div class="export-links">
        <h2>Export des données</h2>
        <a href="export_rapport.php?debut=<?php echo urlencode($debut); ?>&fin=<?php echo urlencode($fin); ?>">
            📄 Exporter ce rapport (CSV)
        </a>
        <a href="export_membres.php">
            👥 Exporter la liste des membres
        </a>
        <a href="export_livres.php">
            📚 Exporter le catalogue
        </a>
        <a href="export_emprunts.php?debut=<?php echo urlencode($debut); ?>&fin=<?php echo urlencode($fin); ?>">
            🔄 Exporter les emprunts
        </a>
    </div>
    
    <h2>Actions rapides</h2>
    <ul>
        <li><a href="generer_rapport_pdf.php?debut=<?php echo urlencode($debut); ?>&fin=<?php echo urlencode($fin); ?>">
            Générer un rapport PDF
        </a></li>
        <li><a href="statistiques_avancees.php">
            Statistiques avancées
        </a></li>
        <li><a href="tableau_bord_stats.php">
            Tableau de bord interactif
        </a></li>
    </ul>
    
    <hr>
    <p>
        <a href="admin_dashboard.php">← Retour au tableau de bord admin</a> | 
        <a href="dashboard.php">Tableau de bord général</a>
    </p>
    
    <!-- Debug info (visible seulement en développement) -->
    <div style="margin-top: 30px; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; font-size: 12px; color: #666;">
        <h4>Debug Info :</h4>
        <p>Base de données : <?php echo $pdo->query("SELECT DATABASE()")->fetchColumn(); ?></p>
        <p>Tables détectées : 
            <?php 
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            echo implode(', ', $tables);
            ?>
        </p>
    </div>
</body>
</html>
<?php require_once 'footer.php'; ?>