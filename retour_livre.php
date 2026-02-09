<?php
session_start();
require_once 'connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$success = false;

// Traitement du retour de livre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retourner'])) {
    $code_barre = $_POST['code_barre'];
    
    try {
        // Rechercher l'emprunt en cours avec ce code barre
        $stmt = $pdo->prepare("
            SELECT e.*, m.nom, m.prenom, l.titre 
            FROM emprunts e
            JOIN exemplaires ex ON e.exemplaire_id = ex.id
            JOIN livres l ON ex.livre_id = l.id
            JOIN membres m ON e.membre_id = m.id
            WHERE ex.code_barre = :code_barre 
            AND e.statut = 'en_cours'
            ORDER BY e.date_emprunt DESC
            LIMIT 1
        ");
        $stmt->execute([':code_barre' => $code_barre]);
        $emprunt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($emprunt) {
            // Calculer les jours de retard
            $date_retour_prevue = new DateTime($emprunt['date_retour_prevue']);
            $date_retour = new DateTime();
            $jours_retard = $date_retour_prevue->diff($date_retour)->days;
            
            if ($date_retour > $date_retour_prevue) {
                $jours_retard = $date_retour->diff($date_retour_prevue)->days;
                $taux_retard = 0.50; // À récupérer de la table paramètres
                $amende = min($jours_retard * $taux_retard, 10.00);
                
                // Créer une amende
                $stmt = $pdo->prepare("
                    INSERT INTO amendes (membre_id, emprunt_id, type, montant, description, date_creation, date_echeance, statut, utilisateur_id)
                    VALUES (:membre_id, :emprunt_id, 'retard', :montant, :description, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'impaye', :utilisateur_id)
                ");
                $stmt->execute([
                    ':membre_id' => $emprunt['membre_id'],
                    ':emprunt_id' => $emprunt['id'],
                    ':montant' => $amende,
                    ':description' => "Retard de $jours_retard jour(s)",
                    ':utilisateur_id' => $_SESSION['user_id']
                ]);
                
                $message .= " Amende de $amende € générée pour $jours_retard jour(s) de retard.";
            }
            
            // Mettre à jour l'emprunt
            $stmt = $pdo->prepare("
                UPDATE emprunts 
                SET date_retour_effective = CURDATE(), 
                    statut = 'retourne',
                    amende_cumulee = :amende
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $emprunt['id'],
                ':amende' => $amende ?? 0
            ]);
            
            // Remettre l'exemplaire disponible
            $stmt = $pdo->prepare("
                UPDATE exemplaires 
                SET statut = 'disponible' 
                WHERE id = :exemplaire_id
            ");
            $stmt->execute([':exemplaire_id' => $emprunt['exemplaire_id']]);
            
            // Journaliser l'action
            $stmt = $pdo->prepare("
                INSERT INTO logs_audit (table_name, record_id, action, user_id, user_type)
                VALUES ('emprunts', :record_id, 'UPDATE', :user_id, 'staff')
            ");
            $stmt->execute([
                ':record_id' => $emprunt['id'],
                ':user_id' => $_SESSION['user_id']
            ]);
            
            $success = true;
            $message = "Livre '{$emprunt['titre']}' retourné avec succès par {$emprunt['prenom']} {$emprunt['nom']}." . $message;
        } else {
            $message = "Aucun emprunt en cours trouvé pour ce code barre.";
        }
    } catch (PDOException $e) {
        $message = "Erreur lors du retour: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retour de livre - Bibliotech</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .message { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Retour de livre</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="code_barre">Code barre du livre à retourner:</label>
                <input type="text" id="code_barre" name="code_barre" required autofocus>
            </div>
            
            <button type="submit" name="retourner">Enregistrer le retour</button>
        </form>
        
        <p><a href="index.php">← Retour au tableau de bord</a></p>
    </div>
</body>
</html>