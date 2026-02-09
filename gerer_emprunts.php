<?php
session_start();
require_once 'connexion.php';
require_once 'header.php';
// Vérifier si staff
if(!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'bibliothecaire' && $_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin')) {
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';

// NOUVEL EMPRUNT
if(isset($_POST['nouvel_emprunt'])) {
    $membre_id = $_POST['membre_id'];
    $exemplaire_id = $_POST['exemplaire_id'];
    
    // Vérifier si exemplaire disponible
    $check_exemplaire = $pdo->prepare("SELECT id FROM exemplaires WHERE id = ? AND statut = 'disponible'");
    $check_exemplaire->execute([$exemplaire_id]);
    
    if($check_exemplaire->fetch()) {
        // Vérifier membre
        $check_membre = $pdo->prepare("SELECT id FROM membres WHERE id = ? AND statut = 'actif'");
        $check_membre->execute([$membre_id]);
        
        if($check_membre->fetch()) {
            try {
                // Créer emprunt
                $stmt = $pdo->prepare("
                    INSERT INTO emprunts (membre_id, exemplaire_id, date_emprunt, date_retour_prevue, statut, utilisateur_id)
                    VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'en_cours', ?)
                ");
                $stmt->execute([$membre_id, $exemplaire_id, $_SESSION['user_id']]);
                
                // Mettre à jour exemplaire
                $pdo->prepare("UPDATE exemplaires SET statut = 'emprunte' WHERE id = ?")->execute([$exemplaire_id]);
                
                $message = "Emprunt créé avec succès !";
            } catch(Exception $e) {
                $error = "Erreur: " . $e->getMessage();
            }
        } else {
            $error = "Membre non trouvé ou inactif";
        }
    } else {
        $error = "Exemplaire non disponible";
    }
}

// RETOURNER LIVRE
if(isset($_POST['retourner'])) {
    $emprunt_id = $_POST['emprunt_id'];
    
    try {
        // Récupérer exemplaire
        $get_exemplaire = $pdo->prepare("SELECT exemplaire_id FROM emprunts WHERE id = ?");
        $get_exemplaire->execute([$emprunt_id]);
        $exemplaire_id = $get_exemplaire->fetchColumn();
        
        if($exemplaire_id) {
            // Marquer retourné
            $pdo->prepare("UPDATE emprunts SET date_retour_effective = CURDATE(), statut = 'retourne' WHERE id = ?")->execute([$emprunt_id]);
            
            // Remettre exemplaire disponible
            $pdo->prepare("UPDATE exemplaires SET statut = 'disponible' WHERE id = ?")->execute([$exemplaire_id]);
            
            $message = "Livre retourné avec succès";
        }
    } catch(Exception $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// RÉCUPÉRER LES DONNÉES

// Emprunts en cours (version SIMPLIFIÉE)
try {
    $emprunts = $pdo->query("
        SELECT 
            e.id as emprunt_id,
            e.date_emprunt,
            e.date_retour_prevue,
            e.statut,
            m.nom as membre_nom,
            m.prenom as membre_prenom,
            m.numero_membre,
            l.titre as livre_titre,
            ex.code_barre
        FROM emprunts e
        JOIN membres m ON e.membre_id = m.id
        JOIN exemplaires ex ON e.exemplaire_id = ex.id
        JOIN livres l ON ex.livre_id = l.id
        WHERE e.statut = 'en_cours'
        ORDER BY e.date_retour_prevue
    ")->fetchAll();
} catch(Exception $e) {
    $emprunts = [];
    $error = "Erreur emprunts: " . $e->getMessage();
}

// Exemplaires disponibles (version SIMPLIFIÉE - sans auteur)
try {
    $exemplaires = $pdo->query("
        SELECT 
            ex.id as exemplaire_id,
            ex.code_barre,
            l.id as livre_id,
            l.titre,
            l.isbn
        FROM exemplaires ex
        JOIN livres l ON ex.livre_id = l.id
        WHERE ex.statut = 'disponible'
        ORDER BY l.titre, ex.code_barre
    ")->fetchAll();
} catch(Exception $e) {
    $exemplaires = [];
    $error = "Erreur exemplaires: " . $e->getMessage();
}

// Membres actifs
try {
    $membres = $pdo->query("
        SELECT id, nom, prenom, numero_membre, email, type_membre
        FROM membres 
        WHERE statut = 'actif'
        ORDER BY nom, prenom
    ")->fetchAll();
} catch(Exception $e) {
    $membres = [];
    $error = "Erreur membres: " . $e->getMessage();
}

// Statistiques
$stats_emprunts = count($emprunts);
$stats_exemplaires = count($exemplaires);
$stats_membres = count($membres);
$stats_retards = 0;
foreach($emprunts as $e) {
    if(strtotime($e['date_retour_prevue']) < time()) {
        $stats_retards++;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gérer les emprunts</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #2b848e;
            --primary-dark: #1e6a72;
            --success: #5aa36a;
            --danger: #c94f4f;
            --light: #f8fafc;
            --dark: #254c5b;
            --gray: #e2e8f0;
            --radius: 10px;
            --shadow: 0 6px 20px rgba(37,76,91,0.12);
        }
        body {
            margin: 0;
            font-family: system-ui, sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        h1, h2 {
            color: var(--primary);
            margin-bottom: 1rem;
        }
        .alert {
            padding: 16px;
            border-radius: var(--radius);
            margin: 1.5rem 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #14532d; }
        .alert-danger  { background: #fff5f5; border: 1px solid #fecaca; color: #7f1d1d; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--gray);
        }
        th {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }
        tr:hover { background: #f8fafc; }
        .btn {
            padding: 10px 18px;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary   { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-danger    { background: var(--danger); color: white; }
        .btn-outline   { border: 2px solid var(--primary); color: var(--primary); background: transparent; }
        .btn-outline:hover { background: var(--primary); color: white; }
        select, button {
            padding: 10px;
            border-radius: var(--radius);
            border: 1px solid #ccc;
            font-size: 1rem;
        }
        form { margin: 1.5rem 0; }
        .stats {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.2rem 1.8rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            min-width: 180px;
        }
        .stat-number { font-size: 2rem; font-weight: 700; color: var(--primary); }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 12px;
            border-radius: var(--radius);
            margin: 1rem 0;
        }
        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { border: 1px solid #ccc; margin-bottom: 1rem; border-radius: var(--radius); }
            td { border: none; border-bottom: 1px solid #eee; position: relative; padding-left: 50%; }
            td:before { position: absolute; top: 12px; left: 16px; width: 45%; padding-right: 10px; white-space: nowrap; font-weight: bold; }
            td:nth-of-type(1):before { content: "Membre"; }
            td:nth-of-type(2):before { content: "Livre"; }
            td:nth-of-type(3):before { content: "Code barre"; }
            td:nth-of-type(4):before { content: "Emprunt"; }
            td:nth-of-type(5):before { content: "Retour prévu"; }
            td:nth-of-type(6):before { content: "Statut"; }
            td:nth-of-type(7):before { content: "Action"; }
        }
    </style>
</head>
<body>
    <h1>Gestion des emprunts</h1>
    
    <p><a href="dashboard.php">Tableau de bord</a> | <a href="add_test_data.php">Ajouter données test</a></p>
    
    <?php if($message): ?>
        <p style="color:green;"><strong><?php echo $message; ?></strong></p>
    <?php endif; ?>
    
    <?php if($error): ?>
        <p style="color:red;"><strong><?php echo $error; ?></strong></p>
    <?php endif; ?>
    
    <h2>Statistiques</h2>
    <p>Emprunts en cours: <?php echo $stats_emprunts; ?> (dont <?php echo $stats_retards; ?> retards)</p>
    <p>Exemplaires disponibles: <?php echo $stats_exemplaires; ?></p>
    <p>Membres actifs: <?php echo $stats_membres; ?></p>
    
    <hr>
    
    <h2>Nouvel emprunt</h2>
    
    <?php if($stats_exemplaires == 0): ?>
        <p style="color:red; background:#ffe6e6; padding:10px;">
            <strong>ATTENTION:</strong> Aucun exemplaire disponible. 
            <a href="ajouter_livre.php">Ajouter des livres</a> ou 
            <a href="ajouter_exemplaire.php">ajouter des exemplaires</a>.
        </p>
    <?php endif; ?>
    
    <?php if($stats_membres == 0): ?>
        <p style="color:red; background:#ffe6e6; padding:10px;">
            <strong>ATTENTION:</strong> Aucun membre actif. 
            <a href="ajouter_membre.php">Ajouter des membres</a>.
        </p>
    <?php endif; ?>
    
    <form method="POST">
        <p>
            <strong>Membre:</strong><br>
            <select name="membre_id" required>
                <option value="">-- Choisir un membre --</option>
                <?php foreach($membres as $m): ?>
                <option value="<?php echo $m['id']; ?>">
                    <?php echo $m['nom'] . ' ' . $m['prenom']; ?> 
                    (<?php echo $m['numero_membre']; ?> - <?php echo $m['type_membre']; ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </p>
        
        <p>
            <strong>Exemplaire:</strong><br>
            <select name="exemplaire_id" required>
                <option value="">-- Choisir un exemplaire --</option>
                <?php foreach($exemplaires as $ex): ?>
                <option value="<?php echo $ex['exemplaire_id']; ?>">
                    <?php echo $ex['titre']; ?> 
                    - Code: <?php echo $ex['code_barre']; ?> 
                    - ISBN: <?php echo $ex['isbn']; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </p>
        
        <button type="submit" name="nouvel_emprunt">Créer l'emprunt (14 jours)</button>
    </form>
    
    <hr>
    
    <h2>Emprunts en cours (<?php echo $stats_emprunts; ?>)</h2>
    
    <?php if($stats_emprunts > 0): ?>
        <table border="1" style="width:100%;">
            <tr>
                <th>Membre</th>
                <th>Livre</th>
                <th>Code barre</th>
                <th>Date emprunt</th>
                <th>Retour prévu</th>
                <th>Statut</th>
                <th>Action</th>
            </tr>
            <?php foreach($emprunts as $e): 
                $retard = strtotime($e['date_retour_prevue']) < time();
                $jours = $retard ? floor((time() - strtotime($e['date_retour_prevue'])) / 86400) : 0;
            ?>
            <tr>
                <td><?php echo $e['membre_nom'] . ' ' . $e['membre_prenom']; ?><br>
                    <small><?php echo $e['numero_membre']; ?></small>
                </td>
                <td><?php echo $e['livre_titre']; ?></td>
                <td><?php echo $e['code_barre']; ?></td>
                <td><?php echo $e['date_emprunt']; ?></td>
                <td>
                    <?php echo $e['date_retour_prevue']; ?>
                    <?php if($retard): ?>
                        <br><strong style="color:red;">RETARD: <?php echo $jours; ?> jour(s)</strong>
                    <?php endif; ?>
                </td>
                <td><?php echo $e['statut']; ?></td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="emprunt_id" value="<?php echo $e['emprunt_id']; ?>">
                        <button type="submit" name="retourner">Retourner</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Aucun emprunt en cours.</p>
    <?php endif; ?>
    
    <hr>
    
    <h2>Exemplaires disponibles (<?php echo $stats_exemplaires; ?>)</h2>
    
    <?php if($stats_exemplaires > 0): ?>
        <table border="1" style="width:100%;">
            <tr>
                <th>Livre</th>
                <th>ISBN</th>
                <th>Code barre</th>
            </tr>
            <?php foreach($exemplaires as $ex): ?>
            <tr>
                <td><?php echo $ex['titre']; ?></td>
                <td><?php echo $ex['isbn']; ?></td>
                <td><?php echo $ex['code_barre']; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>Aucun exemplaire disponible.</p>
        <p><a href="ajouter_livre.php">Ajouter un livre</a> | <a href="ajouter_exemplaire.php">Ajouter un exemplaire</a></p>
    <?php endif; ?>
    
    <hr>
    
    <h2>Actions rapides</h2>
    <ul>
        <li><a href="ajouter_membre.php">Ajouter un membre</a></li>
        <li><a href="ajouter_livre.php">Ajouter un livre</a></li>
        <li><a href="ajouter_exemplaire.php">Ajouter un exemplaire</a></li>
        <li><a href="liste_membres.php">Voir tous les membres</a></li>
        <li><a href="liste_livres.php">Voir tous les livres</a></li>
    </ul>
    
    <p><a href="admin_dashboard.php">← Retour au tableau de bord</a></p>
</body>
</html>
<?php require_once 'footer.php'; ?>