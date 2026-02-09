<?php
session_start();
require_once 'connexion.php';
require_once 'header.php';

// Protection staff
if (!isset($_SESSION['user_id']) || 
    !in_array($_SESSION['role'], ['bibliothecaire', 'admin', 'super_admin'])) {
    header("Location: index.php");
    exit;
}

$message = '';
$error = '';
$membre = null;
$csrf_token = $_SESSION['csrf_suppression'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_suppression'] = $csrf_token;

$membre_id = (int)($_GET['id'] ?? 0);

if ($membre_id <= 0) {
    $error = "Aucun membre sélectionné.";
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmer_suppression'])) {
        // Vérif CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
            $error = "Erreur de sécurité. Rechargez la page.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM membres WHERE id = ?");
                $stmt->execute([$membre_id]);

                $message = "Membre supprimé avec succès.";
                $membre = null; // plus besoin d'afficher
            } catch (PDOException $e) {
                $error = "Erreur lors de la suppression : " . $e->getMessage();
            }
        }
    } else {
        // Afficher infos pour confirmation
        $stmt = $pdo->prepare("
            SELECT nom, prenom, email, telephone, type_membre, numero_membre 
            FROM membres 
            WHERE id = ?
        ");
        $stmt->execute([$membre_id]);
        $membre = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$membre) {
            $error = "Membre non trouvé.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer un membre – Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #2b848e;
            --primary-dark: #1e6a72;
            --success: #7ab781;
            --danger: #c94f4f;
            --light: #f8f9fa;
            --dark: #254c5b;
            --gray: #e9ecef;
            --radius: 10px;
            --shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        body {
            margin: 0;
            font-family: system-ui, sans-serif;
            background: var(--light);
            color: var(--dark);
            padding: 40px 20px;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
        }
        h1 {
            color: var(--primary);
            text-align: center;
            margin-bottom: 30px;
        }
        .card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 30px;
        }
        .info p {
            margin: 12px 0;
            font-size: 1.05rem;
        }
        .info strong {
            display: inline-block;
            width: 140px;
            color: #555;
        }
        .alert {
            padding: 15px;
            border-radius: var(--radius);
            margin-bottom: 20px;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        .alert-danger {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        .buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s;
        }
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-danger:hover {
            background: #b02a37;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .back {
            text-align: center;
            margin-top: 30px;
            font-size: 1.1rem;
        }
        .back a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }
        @media (max-width: 600px) {
            .buttons { flex-direction: column; gap: 12px; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Supprimer un membre</h1>

    <?php if ($message): ?>
        <div class="alert alert-success">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($membre && !isset($_POST['confirmer_suppression'])): ?>
        <div class="card">
            <h2>Confirmer la suppression</h2>
            <div class="info">
                <p><strong>Nom :</strong> <?= htmlspecialchars($membre['prenom'] . ' ' . $membre['nom']) ?></p>
                <p><strong>Numéro :</strong> <?= htmlspecialchars($membre['numero_membre']) ?></p>
                <p><strong>Email :</strong> <?= htmlspecialchars($membre['email']) ?></p>
                <p><strong>Téléphone :</strong> <?= htmlspecialchars($membre['telephone'] ?: 'Non renseigné') ?></p>
                <p><strong>Type :</strong> <?= htmlspecialchars($membre['type_membre']) ?></p>
            </div>

            <p style="color:#dc3545;font-weight:bold;margin-top:20px;">
                Êtes-vous sûr de vouloir supprimer ce membre ? Cette action est irréversible.
            </p>

            <div class="buttons">
                <form method="POST">
                    <input type="hidden" name="id" value="<?= $membre_id ?>">
                    <input type="hidden" name="confirmer_suppression" value="1">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <button type="submit" class="btn btn-danger">Oui, supprimer définitivement</button>
                </form>

                <a href="liste_membres.php" class="btn btn-secondary">Non, annuler</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="back">
        <a href="liste_membres.php">← Retour à la liste des membres</a>
    </div>
</div>

</body>
</html>