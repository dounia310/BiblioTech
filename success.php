<?php
// success.php - Page de succès après paiement
session_start(); // Toujours en premier

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Inclure la connexion à la base de données
require_once 'connexion.php';

// Récupérer les informations de la commande si disponibles
$commande_id = isset($_GET['commande_id']) ? intval($_GET['commande_id']) : 0;
$transaction_id = isset($_GET['transaction_id']) ? $_GET['transaction_id'] : '';

// Mettre à jour le statut de la commande si nécessaire
if ($commande_id > 0) {
    try {
        $stmt = $pdo->prepare("
            UPDATE commandes 
            SET statut = 'payee', 
                date_paiement = NOW() 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$commande_id, $_SESSION['user_id']]);
        
        // Vider le panier après paiement réussi
        if (isset($_SESSION['cart'])) {
            unset($_SESSION['cart']);
        }
        
        // Récupérer les informations de la commande
        $stmt = $pdo->prepare("
            SELECT c.*, u.nom, u.prenom, u.email 
            FROM commandes c
            JOIN utilisateurs u ON c.user_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$commande_id]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        // Log de l'erreur mais continuer à afficher la page de succès
        error_log("Erreur mise à jour commande: " . $e->getMessage());
        $commande = null;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Réussi - Bibliotech</title>
    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --accent: #e8b86d;
            --success: #10b981;
            --light: #ffffff;
            --gray-100: #f1f5f9;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .success-container {
            background: var(--light);
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 600px;
            width: 100%;
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: bounce 1s ease;
        }

        .success-icon i {
            font-size: 50px;
            color: var(--light);
        }

        h1 {
            color: var(--primary);
            margin-bottom: 15px;
            font-size: 2.5rem;
        }

        .message {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .details {
            background: var(--gray-100);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: left;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .detail-label {
            color: #64748b;
            font-weight: 500;
        }

        .detail-value {
            color: var(--primary);
            font-weight: 600;
        }

        .buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 15px 30px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--light);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(30, 58, 95, 0.2);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: var(--light);
        }

        .confirmation-message {
            margin-top: 20px;
            padding: 15px;
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border-radius: 10px;
            color: #065f46;
            font-size: 0.9rem;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }

        @media (max-width: 768px) {
            .success-container {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        
        <h1>Paiement Réussi !</h1>
        
        <div class="message">
            <p>Merci pour votre achat. Votre commande a été traitée avec succès.</p>
            <p>Vous recevrez un email de confirmation sous peu.</p>
        </div>
        
        <div class="details">
            <div class="detail-item">
                <span class="detail-label">Date :</span>
                <span class="detail-value"><?= date('d/m/Y à H:i') ?></span>
            </div>
            
            <?php if ($commande_id > 0): ?>
                <div class="detail-item">
                    <span class="detail-label">Numéro de commande :</span>
                    <span class="detail-value">CMD<?= str_pad($commande_id, 6, '0', STR_PAD_LEFT) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($transaction_id)): ?>
                <div class="detail-item">
                    <span class="detail-label">ID de transaction :</span>
                    <span class="detail-value"><?= htmlspecialchars($transaction_id) ?></span>
                </div>
            <?php endif; ?>
            
            <div class="detail-item">
                <span class="detail-label">Statut :</span>
                <span class="detail-value" style="color: var(--success); font-weight: 700;">
                    <i class="fas fa-check-circle"></i> Payé
                </span>
            </div>
            
            <?php if (isset($commande) && $commande): ?>
                <div class="detail-item">
                    <span class="detail-label">Client :</span>
                    <span class="detail-value"><?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Total :</span>
                    <span class="detail-value"><?= number_format($commande['montant_total'], 2) ?> DH</span>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="confirmation-message">
            <i class="fas fa-info-circle"></i>
            Un email de confirmation a été envoyé à votre adresse email.
        </div>
        
        <div class="buttons" style="margin-top: 30px;">
            <a href="index.php" class="btn btn-primary">
                <i class="fas fa-home"></i> Retour à l'accueil
            </a>
            <a href="mes_achats.php" class="btn btn-secondary">
                <i class="fas fa-shopping-bag"></i> Voir mes achats
            </a>
        </div>
    </div>
</body>
</html>