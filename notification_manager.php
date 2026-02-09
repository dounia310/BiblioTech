<?php
// notification_manager.php
// Version basique - sans classes externes - pour éviter les erreurs Intelephense

session_start();

// Définition de la constante manquante
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/gestion_bibliotheque/');
}

// Connexion PDO simple (à adapter avec tes identifiants réels)
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=bibliotech;charset=utf8mb4",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Erreur de connexion à la base : " . $e->getMessage());
}

// Fonction simple pour ajouter une notification (remplace Log + Database)
function ajouter_notification($pdo, $message) {
    $stmt = $pdo->prepare("
        INSERT INTO notifications (message, created_at)
        VALUES (?, NOW())
    ");
    $stmt->execute([$message]);
    return $pdo->lastInsertId();
}

// Fonction basique pour envoyer un email (sans EmailService)
function envoyer_email_simple($destinataire, $sujet, $message) {
    $headers = "From: no-reply@bibliotech.local\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // ATTENTION : mail() ne fonctionne pas toujours sur localhost
    // Pour tester vraiment → utilise un outil comme Mailtrap ou Papercut
    $envoye = mail($destinataire, $sujet, $message, $headers);
    
    return $envoye ? true : false;
}

// Exemple d'utilisation : ajouter une notification + email quand on appelle ce fichier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    $action = $_POST['action'];
    $message_notif = '';
    $email_envoye = false;
    
    if ($action === 'nouvelle_reservation') {
        $livre = $_POST['livre'] ?? 'Livre inconnu';
        $membre = $_POST['membre'] ?? 'Membre inconnu';
        
        $message_notif = "Nouvelle réservation : $livre par $membre";
        
        // 1) Ajouter dans la table notifications
        $notif_id = ajouter_notification($pdo, $message_notif);
        
        // 2) Envoyer email simple (à un admin fixe pour tester)
        $admin_email = 'admin@bibliotech.local'; // ← change par ton email de test
        $sujet = "Nouvelle réservation sur Bibliotech";
        $contenu = "<h3>Nouvelle réservation</h3><p>$message_notif</p><p>Voir : <a href='" . SITE_URL . "admin_notifications.php'>Notifications</a></p>";
        
        $email_envoye = envoyer_email_simple($admin_email, $sujet, $contenu);
    }
    
    // Réponse JSON basique pour AJAX ou redirection
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $message_notif,
        'notification_id' => $notif_id ?? null,
        'email_sent' => $email_envoye
    ]);
    exit;
}

// Si pas de POST → affichage simple pour test (page admin basique)
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion Notifications - Version Basique</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        h1 { color: #2c3e50; }
        .box { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background: #f9f9f9; }
        .success { color: green; font-weight: bold; }
        button { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #2980b9; }
    </style>
</head>
<body>

<h1>Gestion des notifications (version simple)</h1>

<div class="box">
    <h3>Test rapide : simuler une réservation</h3>
    
    <form method="post">
        <input type="hidden" name="action" value="nouvelle_reservation">
        <p>Livre : <input type="text" name="livre" value="PHP pour les Nuls" required></p>
        <p>Membre : <input type="text" name="membre" value="Imad" required></p>
        <button type="submit">Créer notification + envoyer email</button>
    </form>
    
    <p style="margin-top: 30px;">
        <a href="admin_notifications.php">→ Voir toutes les notifications</a>
    </p>
</div>

</body>
</html>