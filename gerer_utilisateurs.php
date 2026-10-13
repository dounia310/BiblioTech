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
    <style>
    .notifications-bell {
        position: relative;
        cursor: pointer;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.2s;
    }

    .notifications-bell:hover {
        background: var(--primary);
        color: white;
    }

    .notification-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: var(--danger);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 12px;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .notifications-dropdown {
        position: absolute;
        top: 60px;
        right: 20px;
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        box-shadow: var(--shadow-md);
        width: 320px;
        max-height: 400px;
        overflow-y: auto;
        z-index: 1000;
    }

    .notifications-dropdown ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .notifications-dropdown li {
        padding: 12px 16px;
        border-bottom: 1px solid var(--gray-200);
    }

    .notifications-dropdown li.non-lue {
        background: rgba(233, 236, 239, 0.5);
        font-weight: 500;
    }

    .notifications-dropdown li a {
        text-decoration: none;
        color: var(--gray-700);
        display: block;
    }

    .notifications-dropdown li small {
        color: var(--gray-500);
        font-size: 0.85rem;
    }

    .dropdown-footer {
        padding: 12px;
        text-align: center;
        border-top: 1px solid var(--gray-200);
    }

    .dropdown-footer a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
    }
</style>

<script>
    function toggleNotifications() {
        const dropdown = document.getElementById('notifications-dropdown');
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    }

    // Fermer le dropdown si clic ailleurs
    document.addEventListener('click', function(e) {
        const bell = document.querySelector('.notifications-bell');
        const dropdown = document.getElementById('notifications-dropdown');
        if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

    // Marquer comme lu (appel AJAX simple - à améliorer si besoin)
    function marquerLue(id) {
        fetch('marquer_lue.php?id=' + id)
            .then(() => {
                // Option : rafraîchir le badge ou supprimer la classe non-lue
            });
    }
</script>
</body>
</html>