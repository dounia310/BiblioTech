<?php
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    die("Accès réservé au développement !");
}
session_start();
require_once 'connexion.php';

// Vérifier si l'utilisateur est admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: index.php');
    exit();
}

$message = '';

// Ajout de données de test
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Ajouter 5 nouveaux membres
        for ($i = 1; $i <= 5; $i++) {
            $numero = 'TEST' . date('Y') . str_pad($i, 4, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("
                INSERT INTO membres (numero_membre, nom, prenom, email, telephone, type_membre, date_inscription, date_expiration, statut, quota_emprunt)
                VALUES (:numero, 'Test', 'Utilisateur$i', :email, '0100000000', 'etudiant', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'actif', 5)
            ");
            $stmt->execute([
                ':numero' => $numero,
                ':email' => "test$i@example.com"
            ]);
        }
        $message .= "5 nouveaux membres ajoutés.<br>";
        
        // Ajouter 3 nouveaux livres
        $livres = [
            ['Le Vieil Homme et la Mer', 'Ernest Hemingway', '9782070360424', 1952],
            ['Voyage au bout de la nuit', 'Louis-Ferdinand Céline', '9782253003193', 1932],
            ['Lolita', 'Vladimir Nabokov', '9782070360425', 1955]
        ];
        
        foreach ($livres as $livre) {
            $stmt = $pdo->prepare("
                INSERT INTO livres (isbn, titre, auteur, annee_publication, langue, statut_bibliographique)
                VALUES (:isbn, :titre, :auteur, :annee, 'français', 'disponible')
            ");
            $stmt->execute([
                ':isbn' => $livre[2],
                ':titre' => $livre[0],
                ':auteur' => $livre[1],
                ':annee' => $livre[3]
            ]);
            
            $livre_id = $pdo->lastInsertId();
            
            // Ajouter un exemplaire
            $code_barre = $livre[2] . '-TEST';
            $stmt = $pdo->prepare("
                INSERT INTO exemplaires (livre_id, code_barre, etat, statut, localisation)
                VALUES (:livre_id, :code_barre, 'neuf', 'disponible', 'Rayon TEST')
            ");
            $stmt->execute([
                ':livre_id' => $livre_id,
                ':code_barre' => $code_barre
            ]);
        }
        $message .= "3 nouveaux livres avec exemplaires ajoutés.<br>";
        
        // Créer 2 emprunts de test
        $stmt = $pdo->prepare("SELECT id FROM membres WHERE email LIKE 'test%@example.com' LIMIT 2");
        $stmt->execute();
        $membres = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $stmt = $pdo->prepare("SELECT id FROM exemplaires WHERE code_barre LIKE '%-TEST' LIMIT 2");
        $stmt->execute();
        $exemplaires = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        for ($i = 0; $i < 2; $i++) {
            $stmt = $pdo->prepare("
                INSERT INTO emprunts (membre_id, exemplaire_id, date_emprunt, date_retour_prevue, utilisateur_id, statut)
                VALUES (:membre_id, :exemplaire_id, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), :user_id, 'en_cours')
            ");
            $stmt->execute([
                ':membre_id' => $membres[$i],
                ':exemplaire_id' => $exemplaires[$i],
                ':user_id' => $_SESSION['user_id']
            ]);
            
            // Mettre à jour le statut de l'exemplaire
            $stmt = $pdo->prepare("UPDATE exemplaires SET statut = 'emprunte' WHERE id = :id");
            $stmt->execute([':id' => $exemplaires[$i]]);
        }
        $message .= "2 emprunts de test créés.<br>";
        
        $pdo->commit();
        $message = "<div style='color: green;'>Données de test ajoutées avec succès!<br>$message</div>";
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "<div style='color: red;'>Erreur: " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter des données de test - Bibliotech</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .warning { background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
        button { background-color: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #218838; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Ajouter des données de test</h1>
        
        <div class="warning">
            <strong>Attention:</strong> Cette fonctionnalité est réservée aux administrateurs.<br>
            Elle ajoutera des données fictives dans la base de données pour les tests.
        </div>
        
        <?php echo $message; ?>
        
        <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir ajouter des données de test?');">
            <p>Cette action va ajouter:</p>
            <ul>
                <li>5 nouveaux membres (test1@example.com à test5@example.com)</li>
                <li>3 nouveaux livres avec exemplaires</li>
                <li>2 emprunts de test</li>
            </ul>
            
            <button type="submit">Ajouter les données de test</button>
        </form>
        
        <p><a href="index.php">← Retour au tableau de bord</a></p>
    </div>
</body>
</html>