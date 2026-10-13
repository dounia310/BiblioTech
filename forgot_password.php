<?php
ob_start();
session_start();

require_once 'connexion.php';

// Configuration email (à adapter selon ton hébergeur)
$site_url = "https://tonsite.ma";  // ← CHANGE ÇA !
$from_email = "no-reply@bibliotech.ma";
$from_name  = "Bibliotech";

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_or_username = trim($_POST['email_or_username'] ?? '');

    if (empty($email_or_username)) {
        $error = "Veuillez entrer votre email ou nom d'utilisateur.";
    } else {
        // Cherche dans utilisateurs (staff) ET membres
        $tables = [
            ['table' => 'utilisateurs', 'id_field' => 'id', 'email_field' => 'email', 'username_field' => 'username'],
            ['table' => 'membres',      'id_field' => 'id', 'email_field' => 'email', 'username_field' => 'numero_membre']
        ];

        $user_found = null;
        $user_table = null;
        $user_id    = null;

        foreach ($tables as $t) {
            $stmt = $pdo->prepare("
                SELECT {$t['id_field']}, {$t['email_field']}, {$t['username_field']}
                FROM {$t['table']}
                WHERE {$t['email_field']} = ? OR {$t['username_field']} = ?
            ");
            $stmt->execute([$email_or_username, $email_or_username]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $user_found = $row;
                $user_table = $t;
                $user_id    = $row[$t['id_field']];
                break;
            }
        }

        if (!$user_found) {
            // On ne dit pas "email introuvable" pour éviter l'énumération d'utilisateurs
            $success = "Si cet email existe, un lien de réinitialisation vous a été envoyé.";
        } else {
            // Génère un token sécurisé
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Stocke le token (table de réinitialisation)
            $stmt = $pdo->prepare("
                INSERT INTO password_resets 
                (user_id, user_type, email, token, expires_at, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                ($user_table['table'] === 'utilisateurs' ? 'staff' : 'membre'),
                $user_found[$user_table['email_field']],
                $token,
                $expires
            ]);

            // Lien de réinitialisation
            $reset_link = "$site_url/reset_by_token.php?token=" . urlencode($token);

            // Contenu email
            $subject = "Réinitialisation de votre mot de passe - Bibliotech";
            $message = "
            Bonjour,

            Vous avez demandé à réinitialiser votre mot de passe sur Bibliotech.
            Cliquez sur le lien ci-dessous pour créer un nouveau mot de passe (valable 1 heure) :

            $reset_link

            Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.

            Cordialement,
            L'équipe Bibliotech
            ";

            $headers = "From: $from_name <$from_email>\r\n";
            $headers .= "Reply-To: $from_email\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            // Envoi email
            if (mail($user_found[$user_table['email_field']], $subject, $message, $headers)) {
                $success = "Un lien de réinitialisation vous a été envoyé par email (vérifiez vos spams).";
            } else {
                $error = "Erreur lors de l'envoi de l'email. Veuillez réessayer plus tard.";
                // Log pour debug (optionnel)
                error_log("Échec envoi email reset à " . $user_found[$user_table['email_field']]);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e3a5f;
            --accent: #e8b86d;
            --success: #10b981;
            --error: #ef4444;
            --gray-500: #64748b;
            --light: #ffffff;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        h1 {
            text-align: center;
            color: var(--primary);
            margin-bottom: 8px;
        }
        .subtitle {
            text-align: center;
            color: var(--gray-500);
            margin-bottom: 30px;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            text-align: center;
        }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-danger   { background: #fee2e2; color: #991b1b; }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        input[type="text"], input[type="email"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
        }
        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30,58,95,0.15);
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #152a45;
        }
        .back {
            display: block;
            text-align: center;
            margin-top: 24px;
            color: var(--primary);
            text-decoration: none;
        }
        .back:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="card">
    <h1>Mot de passe oublié ?</h1>
    <p class="subtitle">Entrez votre email ou nom d'utilisateur</p>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="email_or_username">Email ou nom d'utilisateur</label>
            <input type="text" id="email_or_username" name="email_or_username" 
                   placeholder="exemple@bibliotech.ma ou votre identifiant"
                   required autofocus>
        </div>

        <button type="submit" class="btn">Envoyer le lien de réinitialisation</button>
    </form>

    <a href="login.php" class="back">← Retour à la connexion</a>
</div>

</body>
</html>