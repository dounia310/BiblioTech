<?php
session_start();
require_once 'connexion.php';
require_once 'header.php';
// Vérifier si admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';

// Sauvegarde manuelle
if(isset($_POST['sauvegarder'])) {
    // Configuration
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $dbname = 'Bibliotech';
    
    // Nom du fichier
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = 'backups/' . $filename;
    
    // Créer le dossier backups s'il n'existe pas
    if(!is_dir('backups')) {
        mkdir('backups', 0777, true);
    }
    
    // Commande mysqldump
    $command = "mysqldump --host=$host --user=$user --password=$pass $dbname > $filepath";
    
    // Exécuter la commande
    system($command, $output);
    
    if($output === 0) {
        $message = "Sauvegarde créée : $filename";
    } else {
        $error = "Erreur lors de la sauvegarde";
    }
}

// Liste des sauvegardes existantes
$backups = [];
if(is_dir('backups')) {
    $files = scandir('backups');
    foreach($files as $file) {
        if($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
            $backups[] = $file;
        }
    }
    rsort($backups); // Plus récent d'abord
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sauvegarde de la base de données</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Sauvegarde de la base de données</h1>
    
    <?php if($message): ?>
        <p style="color:green"><?php echo $message; ?></p>
    <?php endif; ?>
    
    <?php if($error): ?>
        <p style="color:red"><?php echo $error; ?></p>
    <?php endif; ?>
    
    <h2>Créer une nouvelle sauvegarde</h2>
    <form method="POST">
        <p>Cette opération va créer une sauvegarde complète de la base de données.</p>
        <button type="submit" name="sauvegarder" onclick="return confirm('Créer une nouvelle sauvegarde ?')">
            Créer une sauvegarde maintenant
        </button>
    </form>
    
    <h2>Sauvegardes existantes</h2>
    <?php if(count($backups) > 0): ?>
        <table border="1">
            <tr>
                <th>Fichier</th>
                <th>Taille</th>
                <th>Actions</th>
            </tr>
            <?php foreach($backups as $backup): 
                $filesize = filesize('backups/' . $backup);
                $size = round($filesize / 1024, 2) . ' KB';
            ?>
            <tr>
                <td><?php echo $backup; ?></td>
                <td><?php echo $size; ?></td>
                <td>
                    <a href="backups/<?php echo $backup; ?>" download>Télécharger</a>
                    <a href="?restaurer=<?php echo $backup; ?>" onclick="return confirm('ATTENTION : Restaurer cette sauvegarde écrasera la base actuelle. Continuer ?')">Restaurer</a>
                    <a href="?supprimer=<?php echo $backup; ?>" onclick="return confirm('Supprimer cette sauvegarde ?')">Supprimer</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Aucune sauvegarde trouvée.</p>
    <?php endif; ?>
    
    <h2>Informations système</h2>
    <p>Base de données : Bibliotech</p>
    <p>Date actuelle : <?php echo date('d/m/Y H:i:s'); ?></p>
    <p>Taille totale des sauvegardes : 
        <?php 
        $total_size = 0;
        foreach($backups as $backup) {
            $total_size += filesize('backups/' . $backup);
        }
        echo round($total_size / 1024 / 1024, 2) . ' MB';
        ?>
    </p>
    
    <h2>Restauration</h2>
    <p><strong>Attention :</strong> La restauration écrase complètement la base de données actuelle.</p>
    
    <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0;">
        <strong>⚠ MISE EN GARDE :</strong>
        <ul>
            <li>Faites toujours une sauvegarde avant de restaurer</li>
            <li>La restauration peut prendre plusieurs minutes</li>
            <li>Le site sera indisponible pendant la restauration</li>
            <li>Toutes les données actuelles seront perdues</li>
        </ul>
    </div>
    
    <p>
        <a href="admin_dashboard.php">Retour au tableau de bord admin</a> | 
        <a href="dashboard.php">Tableau de bord général</a>
    </p>
</body>
</html>
<?php require_once 'footer.php'; ?>