<?php
session_start();
require_once 'connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';

// Gestion de l'export
if (isset($_GET['type']) && isset($_GET['format'])) {
    $type = $_GET['type'];
    $format = $_GET['format'];
    
    // Définir les requêtes selon le type
    switch ($type) {
        case 'livres':
            $query = "SELECT 
                l.isbn, l.titre, l.auteur, l.annee_publication, l.langue,
                l.genre_principal, l.note_moyenne, l.popularite,
                COUNT(DISTINCT e.id) as nb_exemplaires,
                COUNT(DISTINCT CASE WHEN e.statut = 'disponible' THEN e.id END) as exemplaires_disponibles,
                COUNT(DISTINCT emp.id) as fois_empruntes
                FROM livres l
                LEFT JOIN exemplaires e ON l.id = e.livre_id
                LEFT JOIN emprunts emp ON e.id = emp.exemplaire_id
                GROUP BY l.id
                ORDER BY l.titre";
            $filename = 'liste_livres';
            break;
            
        case 'membres':
            $query = "SELECT 
                m.numero_membre, m.nom, m.prenom, m.email, m.telephone,
                m.type_membre, m.date_inscription, m.date_expiration, m.statut,
                COUNT(DISTINCT e.id) as emprunts_actuels,
                (SELECT COUNT(*) FROM reservations WHERE membre_id = m.id AND statut IN ('en_attente', 'disponible')) as reservations_actives,
                (SELECT COALESCE(SUM(montant - montant_paye), 0) FROM amendes WHERE membre_id = m.id AND statut IN ('impaye', 'partiel')) as amendes_impayees
                FROM membres m
                LEFT JOIN emprunts e ON m.id = e.membre_id AND e.statut = 'en_cours'
                GROUP BY m.id
                ORDER BY m.nom, m.prenom";
            $filename = 'liste_membres';
            break;
            
        case 'emprunts':
            $query = "SELECT 
                e.id, e.date_emprunt, e.date_retour_prevue, e.date_retour_effective, e.statut,
                m.numero_membre, CONCAT(m.nom, ' ', m.prenom) as membre,
                l.titre, ex.code_barre,
                DATEDIFF(COALESCE(e.date_retour_effective, CURDATE()), e.date_retour_prevue) as jours_retard,
                e.amende_cumulee
                FROM emprunts e
                JOIN membres m ON e.membre_id = m.id
                JOIN exemplaires ex ON e.exemplaire_id = ex.id
                JOIN livres l ON ex.livre_id = l.id
                WHERE e.date_emprunt >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                ORDER BY e.date_emprunt DESC";
            $filename = 'historique_emprunts';
            break;
            
        case 'rapports':
            $query = "SELECT 
                s.date, s.type, s.valeur, s.metadata
                FROM statistiques s
                WHERE s.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ORDER BY s.date DESC, s.type";
            $filename = 'rapports_statistiques';
            break;
            
        case 'logs':
            $query = "SELECT 
                l.timestamp, l.table_name, l.record_id, l.action,
                l.user_id, l.user_type, l.ip_address
                FROM logs_audit l
                WHERE l.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY l.timestamp DESC
                LIMIT 1000";
            $filename = 'logs_audit';
            break;
            
        default:
            $message = "Type d'export non reconnu.";
            break;
    }
    
    if (isset($query)) {
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Journaliser l'export
            $stmt = $pdo->prepare("
                INSERT INTO logs_audit (table_name, action, user_id, user_type, ip_address)
                VALUES ('export', 'EXPORT', :user_id, 'staff', :ip_address)
            ");
            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            // Générer le fichier selon le format
            switch ($format) {
                case 'csv':
                    exportCSV($data, $filename);
                    break;
                case 'excel':
                    exportExcel($data, $filename);
                    break;
                case 'pdf':
                    exportPDF($data, $filename, $type);
                    break;
                default:
                    $message = "Format non supporté.";
            }
        } catch (PDOException $e) {
            $message = "Erreur lors de l'export: " . $e->getMessage();
        }
    }
}

// Fonction d'export CSV
function exportCSV($data, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // En-têtes
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
    }
    
    // Données
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

// Fonction d'export Excel (simplifiée en CSV avec en-têtes Excel)
function exportExcel($data, $filename) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.xls"');
    
    echo '<table border="1">';
    
    // En-têtes
    if (!empty($data)) {
        echo '<tr>';
        foreach (array_keys($data[0]) as $header) {
            echo '<th>' . htmlspecialchars($header) . '</th>';
        }
        echo '</tr>';
    }
    
    // Données
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>';
    exit();
}

// Fonction d'export PDF (requiert TCPDF ou autre bibliothèque)
function exportPDF($data, $filename, $type) {
    // Note: Cette implémentation nécessite l'installation de TCPDF
    // Voici une version simplifiée qui génère du HTML
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.pdf"');
    
    $html = '<html><head><style>
        body { font-family: Arial, sans-serif; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #f2f2f2; padding: 8px; border: 1px solid #ddd; }
        td { padding: 8px; border: 1px solid #ddd; }
    </style></head><body>';
    
    $html .= '<h1>' . ucfirst($type) . ' - Bibliotech</h1>';
    $html .= '<p>Export généré le: ' . date('d/m/Y H:i') . '</p>';
    
    if (!empty($data)) {
        $html .= '<table>';
        $html .= '<tr>';
        foreach (array_keys($data[0]) as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr>';
        
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';
    } else {
        $html .= '<p>Aucune donnée à exporter.</p>';
    }
    
    $html .= '</body></html>';
    
    // En production, utiliser TCPDF ou DOMPDF pour générer un vrai PDF
    echo $html;
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export de données - Bibliotech</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .message { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .export-section { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .export-section h3 { margin-top: 0; color: #007bff; }
        .format-buttons { margin-top: 10px; }
        .btn { display: inline-block; padding: 8px 16px; margin-right: 10px; text-decoration: none; border-radius: 4px; }
        .btn-csv { background-color: #28a745; color: white; }
        .btn-excel { background-color: #17a2b8; color: white; }
        .btn-pdf { background-color: #dc3545; color: white; }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Export de données</h1>
        
        <?php if ($message): ?>
            <div class="message error">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <p>Exportez les données de la bibliothèque dans différents formats.</p>
        
        <!-- Liste des livres -->
        <div class="export-section">
            <h3>📚 Liste des livres</h3>
            <p>Export de tous les livres du catalogue avec leurs statistiques.</p>
            <div class="format-buttons">
                <a href="?type=livres&format=csv" class="btn btn-csv">CSV</a>
                <a href="?type=livres&format=excel" class="btn btn-excel">Excel</a>
                <a href="?type=livres&format=pdf" class="btn btn-pdf">PDF</a>
            </div>
        </div>
        
        <!-- Liste des membres -->
        <div class="export-section">
            <h3>👥 Liste des membres</h3>
            <p>Export de tous les membres avec leurs emprunts en cours et amendes.</p>
            <div class="format-buttons">
                <a href="?type=membres&format=csv" class="btn btn-csv">CSV</a>
                <a href="?type=membres&format=excel" class="btn btn-excel">Excel</a>
                <a href="?type=membres&format=pdf" class="btn btn-pdf">PDF</a>
            </div>
        </div>
        
        <!-- Historique des emprunts -->
        <div class="export-section">
            <h3>📖 Historique des emprunts (6 mois)</h3>
            <p>Export des emprunts des 6 derniers mois.</p>
            <div class="format-buttons">
                <a href="?type=emprunts&format=csv" class="btn btn-csv">CSV</a>
                <a href="?type=emprunts&format=excel" class="btn btn-excel">Excel</a>
                <a href="?type=emprunts&format=pdf" class="btn btn-pdf">PDF</a>
            </div>
        </div>
        
        <!-- Rapports statistiques -->
        <div class="export-section">
            <h3>📊 Rapports statistiques (30 jours)</h3>
            <p>Export des statistiques journalières.</p>
            <div class="format-buttons">
                <a href="?type=rapports&format=csv" class="btn btn-csv">CSV</a>
                <a href="?type=rapports&format=excel" class="btn btn-excel">Excel</a>
                <a href="?type=rapports&format=pdf" class="btn btn-pdf">PDF</a>
            </div>
        </div>
        
        <!-- Logs d'audit -->
        <div class="export-section">
            <h3>🔍 Logs d'audit (7 jours)</h3>
            <p>Export des logs d'activité système.</p>
            <div class="format-buttons">
                <a href="?type=logs&format=csv" class="btn btn-csv">CSV</a>
                <a href="?type=logs&format=excel" class="btn btn-excel">Excel</a>
                <a href="?type=logs&format=pdf" class="btn btn-pdf">PDF</a>
            </div>
        </div>
        
        <!-- Export personnalisé -->
        <div class="export-section">
            <h3>⚙️ Export personnalisé</h3>
            <form method="GET" action="export_custom.php" style="margin-top: 15px;">
                <div style="margin-bottom: 10px;">
                    <label for="custom_query">Requête SQL (réservé aux administrateurs):</label><br>
                    <textarea id="custom_query" name="query" rows="4" style="width: 100%; font-family: monospace;"></textarea>
                </div>
                <div>
                    <select name="format" style="padding: 5px; margin-right: 10px;">
                        <option value="csv">CSV</option>
                        <option value="excel">Excel</option>
                    </select>
                    <button type="submit" style="padding: 5px 15px;">Exporter</button>
                </div>
            </form>
        </div>
        
        <p><a href="index.php">← Retour au tableau de bord</a></p>
    </div>
</body>
</html>