<?php
session_start();
require_once 'connexion.php';
 

// Vérifier si staff
if (!isset($_SESSION['user_id']) || 
    ($_SESSION['role'] != 'bibliothecaire' && $_SESSION['role'] != 'admin')) {
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';

// Fonction validation téléphone (format marocain : 10 chiffres commençant par 05 ou 06)
function is_valid_phone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone); // enlever espaces, -, etc.
    return preg_match('/^0[56][0-9]{8}$/', $phone);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nom       = trim($_POST['nom'] ?? '');
    $prenom    = trim($_POST['prenom'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $type      = $_POST['type_membre'] ?? '';

    // Validation
    if (empty($nom) || empty($prenom) || empty($email)) {
        $error = "Nom, prénom et email sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email invalide.";
    } elseif (!empty($telephone) && !is_valid_phone($telephone)) {
        $error = "Le format du téléphone est incorrect (ex: 0612345678 ou 0654321098).";
    } else {
        // Numéro membre unique
        $numero = 'MEM' . date('Y') . rand(1000, 9999);

        // Expiration 1 an
        $expiration = date('Y-m-d', strtotime('+1 year'));

        // Quota
        $quota = ($type == 'professeur') ? 10 : 5;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO membres 
                (numero_membre, nom, prenom, email, telephone, type_membre, 
                 date_inscription, date_expiration, quota_emprunt, statut)
                VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, 'actif')
            ");
            $stmt->execute([$numero, $nom, $prenom, $email, $telephone, $type, $expiration, $quota]);

            $message = "Membre ajouté ! Numéro : <strong>$numero</strong>";
        } catch (PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un membre</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
             --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --primary-dark: #152a45;
            --accent: #e8b86d;
            --light: #ffffff;
            --danger: #dc2626;
            --warning: #f59e0b;
            --success: #10b981;
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
            min-height: 100vh;
        }
        .container {
            max-width: 680px;
            margin: 60px auto;
            padding: 0 20px;
        }
        h1 {
            color: var(--primary);
            font-size: 2.3rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        .success {
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        form {
            background: white;
            padding: 2.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        .form-group {
            margin-bottom: 1.6rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }
        input, select {
            width: 100%;
            padding: 0.9rem 1.2rem;
            border: 1px solid var(--gray);
            border-radius: var(--radius);
            font-size: 1rem;
        }
        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(43,132,142,0.15);
        }
        .btn {
            width: 100%;
            padding: 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
        }
        .btn:hover {
            background: var(--primary-dark);
        }
        .back {
            text-align: center;
            margin-top: 2.5rem;
            font-size: 1.1rem;
        }
        .back a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        .back a:hover {
            text-decoration: underline;
        }
        @media (max-width: 600px) {
            .container { padding: 0 15px; margin: 40px auto; }
            form { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Ajouter un nouveau membre</h1>

    <?php if ($message): ?>
        <div class="success">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Nom</label>
            <input type="text" name="nom" required value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Prénom</label>
            <input type="text" name="prenom" required value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Téléphone (ex: 0612345678)</label>
            <input type="tel" name="telephone" value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>" placeholder="0612345678">
        </div>
        <div class="form-group">
            <label>Type de membre</label>
            <select name="type_membre" required>
                <option value="etudiant"   <?= ($_POST['type_membre'] ?? '') === 'etudiant'   ? 'selected' : '' ?>>Étudiant</option>
                <option value="professeur" <?= ($_POST['type_membre'] ?? '') === 'professeur' ? 'selected' : '' ?>>Professeur</option>
                <option value="personnel"  <?= ($_POST['type_membre'] ?? '') === 'personnel'  ? 'selected' : '' ?>>Personnel</option>
                <option value="externe"    <?= ($_POST['type_membre'] ?? '') === 'externe'    ? 'selected' : '' ?>>Externe</option>
            </select>
        </div>
        <button type="submit" class="btn">Ajouter le membre</button>
    </form>

    <div class="back">
        <a href="dashboard.php">← Retour au tableau de bord</a>
    </div>
</div>
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