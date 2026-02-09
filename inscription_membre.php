<?php
session_start();
require_once 'connexion.php';
require_once 'header.php';
$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    
    // Validation
    if($password != $confirm) {
        $error = "Les mots de passe ne correspondent pas";
    } else {
        // Vérifier si email existe déjà
        $check = $pdo->prepare("SELECT COUNT(*) FROM membres WHERE email = ?");
        $check->execute([$email]);
        
        if($check->fetchColumn() > 0) {
            $error = "Cet email est déjà utilisé";
        } else {
            // Générer numéro
            $numero = 'MEM' . date('Y') . rand(1000, 9999);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insérer
            $stmt = $pdo->prepare("
                INSERT INTO membres (numero_membre, nom, prenom, email, password_hash, 
                                   date_inscription, date_expiration, type_membre, quota_emprunt)
                VALUES (?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'etudiant', 5)
            ");
            $stmt->execute([$numero, $nom, $prenom, $email, $hash]);
            
            $message = "Inscription réussie ! Votre numéro de membre est : $numero";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Inscription membre</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Inscription comme membre</h1>
    
    <?php if($message): ?>
        <p style="color:green"><?php echo $message; ?></p>
        <p><a href="login.php">Se connecter maintenant</a></p>
    <?php else: ?>
    
    <?php if($error): ?>
        <p style="color:red"><?php echo $error; ?></p>
    <?php endif; ?>
    
    <form method="POST">
        <p>Nom: <input type="text" name="nom" required></p>
        <p>Prénom: <input type="text" name="prenom" required></p>
        <p>Email: <input type="email" name="email" required></p>
        <p>Mot de passe: <input type="password" name="password" required></p>
        <p>Confirmer mot de passe: <input type="password" name="confirm_password" required></p>
        <button type="submit">S'inscrire</button>
    </form>
    
    <?php endif; ?>
    
    <p><a href="index.php">Retour à l'accueil</a> | <a href="login.php">Déjà inscrit ?</a></p>
</body>
</html>
<?php require_once 'footer.php'; ?>