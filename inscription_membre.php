<?php
session_start();
require_once 'connexion.php';

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom      = trim($_POST['nom'] ?? '');
    $prenom   = trim($_POST['prenom'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($nom) || empty($prenom) || empty($email) || empty($password)) {
        $error = "Tous les champs sont obligatoires";
    } elseif ($password !== $confirm) {
        $error = "Les mots de passe ne correspondent pas";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format d'email invalide";
    } else {
        // Vérification email existant
        $check = $pdo->prepare("SELECT 1 FROM membres WHERE email = ? LIMIT 1");
        $check->execute([$email]);

        if ($check->fetch()) {
            $error = "Cet email est déjà utilisé";
        } else {
            $numero = 'MEM' . date('Y') . random_int(1000, 9999);
            $hash   = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO membres 
                (numero_membre, nom, prenom, email, password_hash, 
                 date_inscription, date_expiration, type_membre, quota_emprunt)
                VALUES (?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'etudiant', 5)
            ");

            try {
                $stmt->execute([$numero, $nom, $prenom, $email, $hash]);
                $message = "Inscription réussie ! Votre numéro de membre est : <strong>$numero</strong>";
            } catch (Exception $e) {
                $error = "Erreur lors de l'inscription. Veuillez réessayer.";
                // En production : log $e->getMessage()
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Inscription - Membre</title>
     <link rel="icon" type="image/png" href="favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="favicon.svg" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png" />
    <link rel="manifest" href="site.webmanifest" />

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --primary-dark: #152a45;
            --accent: #e8b86d;
            --accent-hover: #d4a55c;
            --success: #10b981;
            --success-light: #d1fae5;
            --error: #ef4444;
            --error-light: #fee2e2;
            --dark: #0f172a;
            --light: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --radius: 16px;
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(255,255,255,0.05) 0%, transparent 50%);
            pointer-events: none;
        }

        .register-container {
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 1;
        }

        .register-card {
            background: white;
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }

        .register-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-wrapper {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }

        .logo-icon {
            font-size: 2.5rem;
            color: white;
        }

        h1 {
            font-size: 1.875rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .subtitle {
            color: var(--gray-500);
            font-size: 1rem;
        }

        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .alert-success {
            background: var(--success-light);
            color: var(--success);
            border: 1px solid rgba(16,185,129,0.2);
        }

        .alert-danger {
            background: var(--error-light);
            color: var(--error);
            border: 1px solid rgba(239,68,68,0.2);
        }

        .alert i {
            font-size: 1.4rem;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 1.15rem;
            transition: color 0.25s;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(30,58,95,0.12);
        }

        .form-control:focus + .input-icon {
            color: var(--primary);
        }

        .form-control::placeholder {
            color: var(--gray-400);
        }

        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 1.02rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(30,58,95,0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(30,58,95,0.3);
        }

        .links {
            text-align: center;
            margin: 28px 0 12px;
        }

        .link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .link:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }

        .footer {
            text-align: center;
            margin-top: 32px;
            font-size: 0.9rem;
            color: var(--gray-500);
        }

        .back {
            color: var(--gray-500);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.25s;
        }

        .back:hover {
            color: var(--primary);
            gap: 12px;
        }

        .floating-shape {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
            pointer-events: none;
        }

        .shape-1 { width: 320px; height: 320px; top: -160px; right: -120px; }
        .shape-2 { width: 220px; height: 220px; bottom: -110px; left: -70px; }

        @media (max-width: 480px) {
            .register-card { padding: 36px 24px; }
            h1 { font-size: 1.6rem; }
            .logo-wrapper { width: 70px; height: 70px; }
            .logo-icon { font-size: 2.1rem; }
        }
    </style>
</head>
<body>

    <div class="register-container">
        <div class="register-card">

            <div class="floating-shape shape-1"></div>
            <div class="floating-shape shape-2"></div>

            <div class="header">
                <div class="logo-wrapper">
                    <i class="fas fa-user-plus logo-icon"></i>
                </div>
                <h1>Inscription</h1>
                <div class="subtitle">Créez votre compte membre</div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= $message ?>
                </div>
                <div style="text-align:center; margin: 24px 0 16px;">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-arrow-right-to-bracket"></i> Se connecter maintenant
                    </a>
                </div>
            <?php else: ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-triangle-exclamation"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <div class="form-group">
                        <label class="form-label">Nom</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" name="nom" class="form-control" placeholder="Votre nom" required value="<?= htmlspecialchars($nom ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Prénom</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" name="prenom" class="form-control" placeholder="Votre prénom" required value="<?= htmlspecialchars($prenom ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" name="email" class="form-control" placeholder="exemple@universite.ma" required value="<?= htmlspecialchars($email ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Mot de passe</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="password" class="form-control" placeholder="••••••••••••" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirmer le mot de passe</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" name="confirm_password" class="form-control" placeholder="••••••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Créer mon compte
                    </button>
                </form>

                <div class="links">
                    <a href="login.php" class="link">Déjà inscrit ? Se connecter</a>
                </div>

            <?php endif; ?>

            <div class="footer">
                <a href="index.php" class="back">
                    <i class="fas fa-arrow-left"></i> Retour à l'accueil
                </a>
            </div>

        </div>
    </div>

</body>
</html>