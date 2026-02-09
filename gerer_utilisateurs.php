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

// Ajouter un utilisateur
if(isset($_POST['ajouter'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $role = $_POST['role'];
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO utilisateurs (username, password_hash, nom, prenom, role)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$username, $hash, $nom, $prenom, $role]);
        
        $message = "Utilisateur ajouté avec succès";
        
    } catch(PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Supprimer un utilisateur
if(isset($_GET['supprimer'])) {
    $id = $_GET['supprimer'];
    
    // Ne pas pouvoir se supprimer soi-même
    if($id != $_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?")->execute([$id]);
        $message = "Utilisateur supprimé";
    } else {
        $error = "Vous ne pouvez pas vous supprimer vous-même";
    }
}

// Récupérer tous les utilisateurs
$users = $pdo->query("SELECT * FROM utilisateurs ORDER BY role, nom")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gérer le personnel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Gestion du personnel</h1>
    
    <?php if($message): ?>
        <p style="color:green"><?php echo $message; ?></p>
    <?php endif; ?>
    
    <?php if($error): ?>
        <p style="color:red"><?php echo $error; ?></p>
    <?php endif; ?>
    
    <h2>Ajouter un membre du personnel</h2>
    <form method="POST">
        <p>Nom d'utilisateur: <input type="text" name="username" required></p>
        <p>Mot de passe: <input type="password" name="password" required></p>
        <p>Nom: <input type="text" name="nom" required></p>
        <p>Prénom: <input type="text" name="prenom" required></p>
        <p>Rôle:
            <select name="role">
                <option value="admin">Administrateur</option>
                <option value="bibliothecaire">Bibliothécaire</option>
                <option value="assistant">Assistant</option>
            </select>
        </p>
        <button type="submit" name="ajouter">Ajouter</button>
    </form>
    
    <hr>
    
    <h2>Liste du personnel</h2>
    <table border="1">
        <tr>
            <th>Nom d'utilisateur</th>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Rôle</th>
            <th>Date création</th>
            <th>Actions</th>
        </tr>
        <?php foreach($users as $u): ?>
        <tr>
            <td><?php echo $u['username']; ?></td>
            <td><?php echo $u['nom']; ?></td>
            <td><?php echo $u['prenom']; ?></td>
            <td><?php echo $u['role']; ?></td>
            <td><?php echo $u['created_at']; ?></td>
            <td>
                <?php if($u['id'] != $_SESSION['user_id']): ?>
                    <a href="?supprimer=<?php echo $u['id']; ?>" onclick="return confirm('Supprimer cet utilisateur ?')">Supprimer</a>
                <?php else: ?>
                    (Vous)
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <p><a href="admin_dashboard.php">Retour au tableau de bord admin</a></p>
</body>
</html>
<?php require_once 'footer.php'; ?>