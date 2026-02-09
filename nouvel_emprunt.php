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
$membre_info = null;

// Recherche du membre
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rechercher_membre'])) {
    $identifiant = $_POST['identifiant'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   COUNT(e.id) as emprunts_en_cours,
                   (SELECT COUNT(*) FROM reservations WHERE membre_id = m.id AND statut IN ('en_attente', 'disponible')) as reservations_en_cours
            FROM membres m
            LEFT JOIN emprunts e ON m.id = e.membre_id AND e.statut = 'en_cours'
            WHERE (m.numero_membre = :identifiant OR m.email = :identifiant)
            AND m.statut = 'actif'
            GROUP BY m.id
        ");
        $stmt->execute([':identifiant' => $identifiant]);
        $membre_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$membre_info) {
            $message = "Aucun membre actif trouvé avec cet identifiant.";
        }
    } catch (PDOException $e) {
        $message = "Erreur lors de la recherche: " . $e->getMessage();
    }
}

// Scanner un livre pour l'emprunt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scanner_livre']) && isset($_POST['membre_id'])) {
    $code_barre = $_POST['code_barre'];
    $membre_id = $_POST['membre_id'];
    
    try {
        // Vérifier que le membre n'a pas atteint son quota
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as nb_emprunts 
            FROM emprunts 
            WHERE membre_id = :membre_id AND statut = 'en_cours'
        ");
        $stmt->execute([':membre_id' => $membre_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['nb_emprunts'] >= $membre_info['quota_emprunt']) {
            $message = "Le membre a atteint son quota maximum d'emprunts ({$membre_info['quota_emprunt']}).";
        } else {
            // Vérifier si l'exemplaire est disponible
            $stmt = $pdo->prepare("
                SELECT ex.*, l.titre, l.auteur
                FROM exemplaires ex
                JOIN livres l ON ex.livre_id = l.id
                WHERE ex.code_barre = :code_barre
                AND ex.statut = 'disponible'
            ");
            $stmt->execute([':code_barre' => $code_barre]);
            $exemplaire = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($exemplaire) {
                // Créer l'emprunt
                $duree_standard = 14; // À récupérer de la table paramètres
                $date_retour_prevue = date('Y-m-d', strtotime("+$duree_standard days"));
                
                $stmt = $pdo->prepare("
                    INSERT INTO emprunts (membre_id, exemplaire_id, date_emprunt, date_retour_prevue, utilisateur_id, statut)
                    VALUES (:membre_id, :exemplaire_id, CURDATE(), :date_retour_prevue, :utilisateur_id, 'en_cours')
                ");
                $stmt->execute([
                    ':membre_id' => $membre_id,
                    ':exemplaire_id' => $exemplaire['id'],
                    ':date_retour_prevue' => $date_retour_prevue,
                    ':utilisateur_id' => $_SESSION['user_id']
                ]);
                
                $emprunt_id = $pdo->lastInsertId();
                
                // Mettre à jour le statut de l'exemplaire
                $stmt = $pdo->prepare("
                    UPDATE exemplaires 
                    SET statut = 'emprunte' 
                    WHERE id = :id
                ");
                $stmt->execute([':id' => $exemplaire['id']]);
                
                // Journaliser l'action
                $stmt = $pdo->prepare("
                    INSERT INTO logs_audit (table_name, record_id, action, user_id, user_type)
                    VALUES ('emprunts', :record_id, 'INSERT', :user_id, 'staff')
                ");
                $stmt->execute([
                    ':record_id' => $emprunt_id,
                    ':user_id' => $_SESSION['user_id']
                ]);
                
                $success = true;
                $message = "Emprunt créé avec succès! Livre: '{$exemplaire['titre']}' - Date de retour: $date_retour_prevue";
            } else {
                $message = "Livre non disponible ou code barre invalide.";
            }
        }
    } catch (PDOException $e) {
        $message = "Erreur lors de l'emprunt: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvel emprunt - Bibliotech</title>
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
        .membre-info { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Nouvel emprunt</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Étape 1: Recherche du membre -->
        <h2>Étape 1: Trouver le membre</h2>
        <form method="POST">
            <div class="form-group">
                <label for="identifiant">Numéro de membre ou email:</label>
                <input type="text" id="identifiant" name="identifiant" required>
            </div>
            <button type="submit" name="rechercher_membre">Rechercher le membre</button>
        </form>
        
        <?php if ($membre_info): ?>
            <div class="membre-info">
                <h3>Informations du membre</h3>
                <p><strong>Nom:</strong> <?php echo htmlspecialchars($membre_info['prenom'] . ' ' . $membre_info['nom']); ?></p>
                <p><strong>Numéro:</strong> <?php echo htmlspecialchars($membre_info['numero_membre']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($membre_info['email']); ?></p>
                <p><strong>Type:</strong> <?php echo htmlspecialchars($membre_info['type_membre']); ?></p>
                <p><strong>Quota:</strong> <?php echo htmlspecialchars($membre_info['emprunts_en_cours'] . '/' . $membre_info['quota_emprunt']); ?> livres empruntés</p>
                <p><strong>Réservations:</strong> <?php echo htmlspecialchars($membre_info['reservations_en_cours']); ?> en attente</p>
            </div>
            
            <!-- Étape 2: Scanner les livres -->
            <h2>Étape 2: Scanner les livres</h2>
            <form method="POST">
                <input type="hidden" name="membre_id" value="<?php echo $membre_info['id']; ?>">
                <div class="form-group">
                    <label for="code_barre">Scanner le code barre du livre:</label>
                    <input type="text" id="code_barre" name="code_barre" required autofocus>
                </div>
                <button type="submit" name="scanner_livre">Ajouter ce livre</button>
            </form>
        <?php endif; ?>
        
        <p><a href="index.php">← Retour au tableau de bord</a></p>
    </div>
</body>
</html>