<?php
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    die("Connexion requise");
}

$id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($id <= 0) {
    die("ID invalide");
}

try {
    $stmt = $pdo->prepare("
        SELECT c.*, f.chemin_fichier   -- adapte selon ta table fichiers
        FROM commandes c
        LEFT JOIN fichiers f ON ...     -- adapte la jointure (ex: c.livre_id = f.livre_id)
        WHERE c.id = ? 
          AND c.user_id = ? 
          AND c.statut_paiement = 'paye'
          AND c.type = 'achat_numerique'
        LIMIT 1
    ");

    if ($stmt === false) {
        throw new Exception("Échec préparation requête téléchargement");
    }

    $stmt->execute([$id, $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['chemin_fichier'])) {
        die("Accès non autorisé ou fichier introuvable");
    }

    $file_path = 'uploads/livres/' . $row['chemin_fichier'];  // adapte le chemin

    if (!file_exists($file_path)) {
        die("Fichier physique introuvable");
    }

    // Envoi du fichier
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit;

} catch (Exception $e) {
    error_log("Erreur téléchargement : " . $e->getMessage());
    die("Erreur technique lors du téléchargement.");
}
?>