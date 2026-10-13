<?php
// retour_confirmation.php - Page de confirmation de retour de livre
session_start();
require_once 'connexion.php'; // optionnel ici, mais cohérent avec tes autres pages

// Sécurité : si pas de message de succès → redirection
if (!isset($_SESSION['retour_success'])) {
    header('Location: mes_emprunts.php');
    exit;
}

// Récupération des données de session
$message     = $_SESSION['retour_success'] ?? 'Le retour a été enregistré avec succès.';
$livre_titre = $_SESSION['retour_livre']   ?? 'le livre';
$amende      = floatval($_SESSION['retour_amende'] ?? 0);

// Nettoyage immédiat de la session (bonne pratique)
unset($_SESSION['retour_success']);
unset($_SESSION['retour_livre']);
unset($_SESSION['retour_amende']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retour confirmé - Bibliotech</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --accent: #e8b86d;
            --success: #10b981;
            --warning: #f59e0b;
            --gray-100: #f1f5f9;
            --gray-700: #334155;
            --light: #ffffff;
            --radius: 12px;
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.15);
            --transition: 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--gray-700);
        }

        .confirmation-card {
            background: var(--light);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            padding: 3rem 2.5rem;
            max-width: 520px;
            width: 100%;
            text-align: center;
            animation: popIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes popIn {
            0%   { transform: scale(0.7) translateY(40px); opacity: 0; }
            100% { transform: scale(1) translateY(0); opacity: 1; }
        }

        .success-icon {
            font-size: 5.5rem;
            color: var(--success);
            margin-bottom: 1.5rem;
            animation: bounce 1.8s infinite alternate;
        }

        @keyframes bounce {
            from { transform: translateY(0); }
            to   { transform: translateY(-12px); }
        }

        h1 {
            color: var(--primary);
            font-size: 2.1rem;
            margin-bottom: 1rem;
        }

        .message {
            font-size: 1.15rem;
            color: var(--gray-700);
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        .book-title {
            font-weight: 600;
            color: var(--primary);
        }

        .amende-box {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: var(--radius);
            padding: 1.2rem;
            margin: 1.5rem 0;
            font-size: 1.05rem;
        }

        .amende-box strong {
            color: #c2410c;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.9rem 2rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 1.05rem;
            transition: var(--transition);
            box-shadow: 0 4px 12px rgba(30, 58, 95, 0.25);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 58, 95, 0.35);
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--primary);
            box-shadow: none;
            margin-top: 1rem;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        @media (max-width: 480px) {
            .confirmation-card {
                padding: 2.2rem 1.5rem;
            }
            h1 { font-size: 1.8rem; }
            .success-icon { font-size: 4.5rem; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

    <div class="confirmation-card">
        <div class="success-icon">✅</div>
        <h1>Retour confirmé !</h1>
        
        <p class="message">
            Merci ! Le retour de <span class="book-title"><?= htmlspecialchars($livre_titre) ?></span><br>
            a bien été enregistré.
        </p>

        <?php if ($amende > 0): ?>
            <div class="amende-box">
                <strong>Amende à régler :</strong> <?= number_format($amende, 2, ',', ' ') ?> DH<br>
                <small>Vous pouvez la payer depuis la section « Mes amendes »</small>
            </div>
        <?php else: ?>
            <p style="color: var(--success); font-weight: 500; margin: 1.5rem 0;">
                Aucun frais supplémentaire. Merci de votre ponctualité !
            </p>
        <?php endif; ?>

        <a href="mes_emprunts.php" class="btn">
            <i class="fas fa-arrow-left"></i> Retour à mes emprunts
        </a>

        <a href="catalogue.php" class="btn btn-secondary">
            <i class="fas fa-book"></i> Continuer à explorer
        </a>
    </div>

</body>
</html>