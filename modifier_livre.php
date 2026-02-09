<?php
session_start();
require_once 'connexion.php';
// Vérifier si staff
if(!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'bibliothecaire' && $_SESSION['role'] != 'admin')) {
    header("Location: index.php");
    exit();
}

$livre_id = $_GET['id'] ?? 0;
$message = '';
$error = '';

// Récupérer le livre
$stmt = $pdo->prepare("SELECT * FROM livres WHERE id = ?");
$stmt->execute([$livre_id]);
$livre = $stmt->fetch();

if(!$livre) {
    die("Livre non trouvé");
}

// Traitement de la modification
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['modifier'])) {
    $isbn = $_POST['isbn'] ?? null;
    $titre = $_POST['titre'] ?? '';
    $auteur = $_POST['auteur'] ?? null;
    $annee = $_POST['annee_publication'] ?? null;
    $pages = $_POST['nombre_pages'] ?? null;
    $langue = $_POST['langue'] ?? null;
    $description = $_POST['description'] ?? null;
    $prix = $_POST['prix'] ?? null;
    $cote = $_POST['cote'] ?? null;
    $genre = $_POST['genre_principal'] ?? null;
    $statut_biblio = $_POST['statut_bibliographique'] ?? null;
    $couverture_url = $_POST['couverture_url'] ?? null;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE livres 
            SET isbn = ?,
                titre = ?,
                auteur = ?,
                annee_publication = ?,
                nombre_pages = ?,
                langue = ?,
                description = ?,
                prix = ?,
                cote = ?,
                genre_principal = ?,
                statut_bibliographique = ?,
                couverture_url = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $isbn ?: null,
            $titre,
            $auteur ?: null,
            $annee !== '' ? $annee : null,
            $pages !== '' ? $pages : null,
            $langue ?: null,
            $description ?: null,
            $prix !== '' ? $prix : null,
            $cote ?: null,
            $genre ?: null,
            $statut_biblio ?: null,
            $couverture_url ?: null,
            $livre_id
        ]);
        
        $message = "Livre modifié avec succès";
        
        // Recharger les infos
        $stmt = $pdo->prepare("SELECT * FROM livres WHERE id = ?");
        $stmt->execute([$livre_id]);
        $livre = $stmt->fetch();
        
    } catch(PDOException $e) {
        $error = "Erreur lors de la mise à jour.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Modifier un livre</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #2b848e;
            --primary-dark: #1e6a72;
            --success: #28a745;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #254c5b;
            --gray: #e9ecef;
            --radius: 10px;
            --shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        body {
            font-family: system-ui, sans-serif;
            background: var(--light);
            color: var(--dark);
            padding: 2rem 1rem;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            color: var(--primary);
            text-align: center;
            margin-bottom: 2rem;
            font-size: 2.3rem;
        }
        .message {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
        }
        .message.success { background: #d4edda; color: #155724; }
        .message.error   { background: #f8d7da; color: #721c24; }
        form {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        .form-group {
            margin-bottom: 1.6rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.85rem 1rem;
            border: 1px solid var(--gray);
            border-radius: 6px;
            font-size: 1rem;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(43,132,142,0.15);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 110px;
        }
        .form-actions {
            text-align: center;
            margin-top: 2.2rem;
        }
        button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.95rem 2.2rem;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        button:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .links {
            text-align: center;
            margin-top: 2.5rem;
            font-size: 1.05rem;
        }
        .links a {
            margin: 0 1.2rem;
            color: var(--primary);
            text-decoration: none;
        }
        .links a:hover {
            text-decoration: underline;
        }
        @media (max-width: 576px) {
            h1 { font-size: 1.9rem; }
            form { padding: 1.6rem; }
            button { width: 100%; }
        }
    </style>
</head>
<body>
    <h1>Modifier un livre</h1>
    
    <?php if($message): ?>
        <p style="color:green"><?php echo $message; ?></p>
    <?php endif; ?>
    
    <?php if($error): ?>
        <p style="color:red"><?php echo $error; ?></p>
    <?php endif; ?>
    
    <form method="POST">
        <p>ISBN: <input type="text" name="isbn" value="<?php echo htmlspecialchars($livre['isbn'] ?? ''); ?>"></p>
        <p>Titre: <input type="text" name="titre" value="<?php echo htmlspecialchars($livre['titre'] ?? ''); ?>" required></p>
        <p>Auteur: <input type="text" name="auteur" value="<?php echo htmlspecialchars($livre['auteur'] ?? ''); ?>"></p>
        <p>Année: <input type="number" name="annee_publication" value="<?php echo htmlspecialchars($livre['annee_publication'] ?? ''); ?>"></p>
        <p>Nombre de pages: <input type="number" name="nombre_pages" value="<?php echo htmlspecialchars($livre['nombre_pages'] ?? ''); ?>"></p>
        <p>Langue: <input type="text" name="langue" value="<?php echo htmlspecialchars($livre['langue'] ?? ''); ?>"></p>
        <p>Genre: <input type="text" name="genre_principal" value="<?php echo htmlspecialchars($livre['genre_principal'] ?? ''); ?>"></p>
        <p>Prix: <input type="number" step="0.01" name="prix" value="<?php echo htmlspecialchars($livre['prix'] ?? ''); ?>"></p>
        <p>Cote: <input type="text" name="cote" value="<?php echo htmlspecialchars($livre['cote'] ?? ''); ?>"></p>
        <p>Couverture URL: <input type="text" name="couverture_url" value="<?php echo htmlspecialchars($livre['couverture_url'] ?? ''); ?>"></p>
        <p>Statut bibliographique:
            <select name="statut_bibliographique">
                <?php
                $statuts = ['disponible','epuise','nouveaute','a_paraitre','hors_catalogue'];
                $current = $livre['statut_bibliographique'] ?? 'disponible';
                foreach ($statuts as $s) {
                    $selected = ($s === $current) ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($s) . '" ' . $selected . '>' . htmlspecialchars($s) . '</option>';
                }
                ?>
            </select>
        </p>
        <p>Description:<br>
            <textarea name="description" rows="4" cols="50"><?php echo htmlspecialchars($livre['description'] ?? ''); ?></textarea>
        </p>
        
        <button type="submit" name="modifier">Enregistrer les modifications</button>
    </form>
    
    <p>
        <a href="liste_livres.php">Retour à la liste</a> | 
        <a href="dashboard.php">Tableau de bord</a>
    </p>
</body>
</html>