<?php
session_start();
require_once 'connexion.php';
require_once 'header.php';
// Vérifier si admin
if(!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin')) {
    header("Location: index.php");
    exit();
}

// Nombre de logs par page
$logs_par_page = 50;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $logs_par_page;

// Initialiser les variables
$total_logs = 0;
$total_pages = 0;
$logs = [];

try {
    // Récupérer le total des logs
    $total_logs = $pdo->query("SELECT COUNT(*) FROM logs_audit")->fetchColumn();
    $total_pages = ceil($total_logs / $logs_par_page);

    // Récupérer les logs avec pagination
    $stmt = $pdo->prepare("
        SELECT l.*, u.username 
        FROM logs_audit l
        LEFT JOIN utilisateurs u ON l.user_id = u.id
        ORDER BY l.timestamp DESC
        LIMIT :offset, :limit
    ");
    
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $logs_par_page, PDO::PARAM_INT);
    $stmt->execute();
    
    $logs = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Erreur lors du chargement des logs : " . $e->getMessage();
}

// Vider les logs si demandé
if(isset($_GET['vider_logs']) && $_GET['vider_logs'] == 1) {
    try {
        $pdo->exec("TRUNCATE TABLE logs_audit");
        $message = "✅ Tous les logs ont été vidés avec succès";
        header("Location: logs.php?message=" . urlencode($message));
        exit();
    } catch (PDOException $e) {
        $error = "❌ Erreur lors du vidage des logs : " . $e->getMessage();
    }
}

// Récupérer les statistiques des actions
$stats = [];
try {
    $stats_stmt = $pdo->query("
        SELECT action, COUNT(*) as nombre
        FROM logs_audit
        GROUP BY action
        ORDER BY nombre DESC
    ");
    $stats = $stats_stmt->fetchAll();
} catch (Exception $e) {
    // Ignorer l'erreur si la table n'existe pas
}

// Récupérer le message depuis l'URL
if(isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logs système</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>📋 Logs système</h1>
        
        <?php if(isset($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if(isset($error_message)): ?>
            <div class="warning">
                <?php echo $error_message; ?>
                <p><small>Assurez-vous que la table 'logs_audit' existe dans la base de données.</small></p>
            </div>
        <?php endif; ?>
        
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-number"><?php echo $total_logs; ?></div>
                <div class="stat-label">Total des logs</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $total_pages; ?></div>
                <div class="stat-label">Pages</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $logs_par_page; ?></div>
                <div class="stat-label">Logs par page</div>
            </div>
        </div>
        
        <!-- Filtres -->
        <div class="filters">
            <h3>🔍 Filtres</h3>
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="action">Action :</label>
                    <select id="action" name="action">
                        <option value="">Toutes les actions</option>
                        <option value="INSERT">INSERT</option>
                        <option value="UPDATE">UPDATE</option>
                        <option value="DELETE">DELETE</option>
                        <option value="LOGIN">LOGIN</option>
                        <option value="LOGOUT">LOGOUT</option>
                        <option value="EXPORT">EXPORT</option>
                        <option value="IMPORT">IMPORT</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="table">Table :</label>
                    <input type="text" id="table" name="table" placeholder="ex: utilisateurs">
                </div>
                <div class="filter-group">
                    <label for="user">Utilisateur :</label>
                    <input type="text" id="user" name="user" placeholder="Nom d'utilisateur">
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn">Filtrer</button>
                    <a href="logs.php" class="btn">Réinitialiser</a>
                </div>
            </form>
        </div>
        
        <!-- Statistiques des actions -->
        <?php if(count($stats) > 0): ?>
        <div class="actions-container">
            <h3>📊 Statistiques des actions</h3>
            <table>
                <tr>
                    <th>Action</th>
                    <th>Nombre</th>
                    <th>Pourcentage</th>
                </tr>
                <?php 
                $total_actions = array_sum(array_column($stats, 'nombre'));
                foreach($stats as $stat): 
                    $percentage = $total_actions > 0 ? round(($stat['nombre'] / $total_actions) * 100, 1) : 0;
                ?>
                <tr>
                    <td><span class="action-badge action-<?php echo $stat['action']; ?>">
                        <?php echo $stat['action']; ?>
                    </span></td>
                    <td><?php echo $stat['nombre']; ?></td>
                    <td>
                        <div style="background: #e9ecef; border-radius: 4px;">
                            <div style="background: #007bff; width: <?php echo $percentage; ?>%; height: 20px; border-radius: 4px;"></div>
                        </div>
                        <?php echo $percentage; ?>%
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Liste des logs -->
        <h2>📝 Logs récents (page <?php echo $page; ?> sur <?php echo $total_pages; ?>)</h2>
        
        <?php if(count($logs) > 0): ?>
        <table>
            <tr>
                <th>Date/Heure</th>
                <th>Table</th>
                <th>Action</th>
                <th>Utilisateur</th>
                <th>ID Record</th>
                <th>Anciennes valeurs</th>
                <th>Nouvelles valeurs</th>
                <th>IP</th>
            </tr>
            <?php foreach($logs as $log): ?>
            <tr>
                <td><?php echo date('d/m/Y H:i:s', strtotime($log['timestamp'])); ?></td>
                <td><?php echo htmlspecialchars($log['table_name']); ?></td>
                <td>
                    <span class="action-badge action-<?php echo $log['action']; ?>">
                        <?php echo $log['action']; ?>
                    </span>
                </td>
                <td><?php echo htmlspecialchars($log['username'] ?: 'Système'); ?></td>
                <td><?php echo $log['record_id']; ?></td>
                <td>
                    <?php if($log['old_values']): ?>
                        <div class="json-data"><?php echo htmlspecialchars($log['old_values']); ?></div>
                    <?php else: ?>
                        <em>vide</em>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if($log['new_values']): ?>
                        <div class="json-data"><?php echo htmlspecialchars($log['new_values']); ?></div>
                    <?php else: ?>
                        <em>vide</em>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
            <div class="no-data">
                <h3>📭 Aucun log trouvé</h3>
                <p>La table des logs est vide ou n'existe pas encore.</p>
                <p>Les logs sont automatiquement créés lors des actions importantes dans le système.</p>
            </div>
        <?php endif; ?>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?page=<?php echo $page-1; ?>">← Précédent</a>
            <?php endif; ?>
            
            <?php 
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            
            for($i = $start; $i <= $end; $i++):
            ?>
                <?php if($i == $page): ?>
                    <strong><?php echo $i; ?></strong>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?>">Suivant →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Actions -->
        <div class="actions-container">
            <h3>⚙️ Actions</h3>
            <a href="?vider_logs=1" class="btn btn-danger" 
               onclick="return confirm('⚠️ ATTENTION : Cette action est irréversible.\n\nVoulez-vous vraiment vider TOUS les logs ?');">
               🗑️ Vider tous les logs
            </a>
            <a href="export.php?type=logs&format=csv" class="btn btn-success">📥 Exporter les logs (CSV)</a>
            <a href="export.php?type=logs&format=excel" class="btn btn-success">📥 Exporter les logs (JSON)</a>
            <button class="btn" onclick="window.print()">🖨️ Imprimer</button>
        </div>
        
        <!-- Informations système -->
        <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 5px; font-size: 14px;">
            <h3>ℹ️ Informations</h3>
            <p><strong>Base de données :</strong> <?php echo $pdo->query("SELECT DATABASE()")->fetchColumn(); ?></p>
            <p><strong>Date/heure serveur :</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            <p><strong>Taille de la table logs_audit :</strong> 
                <?php 
                try {
                    $size = $pdo->query("SELECT ROUND((data_length + index_length) / 1024 / 1024, 2) as size_mb 
                                        FROM information_schema.TABLES 
                                        WHERE table_schema = DATABASE() 
                                        AND table_name = 'logs_audit'")->fetchColumn();
                    echo $size ? $size . ' MB' : 'Non disponible';
                } catch (Exception $e) {
                    echo 'Non disponible';
                }
                ?>
            </p>
            <p><small>Les logs sont conservés pour le suivi d'audit et la sécurité. Il est recommandé de les archiver régulièrement.</small></p>
        </div>
        
        <p style="margin-top: 30px;">
            <a href="admin_dashboard.php" class="back-link">← Retour au tableau de bord admin</a> | 
            <a href="dashboard.php">Tableau de bord général</a>
        </p>
    </div>
    
    <script>
        // Fonction pour formater le JSON dans les logs
        document.addEventListener('DOMContentLoaded', function() {
            const jsonElements = document.querySelectorAll('.json-data');
            jsonElements.forEach(el => {
                try {
                    const jsonText = el.textContent.trim();
                    if(jsonText) {
                        const jsonObj = JSON.parse(jsonText);
                        el.textContent = JSON.stringify(jsonObj, null, 2);
                    }
                } catch(e) {
                    // Si ce n'est pas du JSON valide, on laisse tel quel
                }
            });
            
            // Restaurer les valeurs des filtres depuis l'URL
            const urlParams = new URLSearchParams(window.location.search);
            const action = urlParams.get('action');
            const table = urlParams.get('table');
            const user = urlParams.get('user');
            
            if(action) document.getElementById('action').value = action;
            if(table) document.getElementById('table').value = table;
            if(user) document.getElementById('user').value = user;
        });
    </script>
</body>
</html>
<?php require_once 'footer.php'; ?>