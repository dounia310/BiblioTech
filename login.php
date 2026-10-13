<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();
session_start();

require_once 'connexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        // 1. Tentative staff / utilisateurs
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && ($user['statut'] ?? 'actif') === 'actif') {
            $password_hash = $user['password_hash'] ?? $user['password'] ?? $user['mot_de_passe'] ?? null;

            if ($password_hash && password_verify($password, $password_hash)) {
                $_SESSION['user_id']   = $user['id'] ?? $user['user_id'] ?? 0;
                $_SESSION['username']  = $user['username'] ?? $user['email'] ?? '';
                $_SESSION['nom']       = $user['nom'] ?? '';
                $_SESSION['prenom']    = $user['prenom'] ?? '';
                $_SESSION['role']      = $user['role'] ?? 'bibliothecaire';
                $_SESSION['type']      = 'staff';

                $role_lower = strtolower(trim($_SESSION['role']));
                $is_admin = in_array($role_lower, ['admin', 'super_admin', 'administrateur', 'superadmin']) ||
                            strtolower($_SESSION['username']) === 'superadmin';
                $_SESSION['is_admin'] = $is_admin ? 1 : 0;

                error_log("Login STAFF réussi - username: {$_SESSION['username']} | role: {$_SESSION['role']} | is_admin: {$_SESSION['is_admin']}");

                $id_field = isset($user['id']) ? 'id' : 'user_id';
                $user_id  = $user[$id_field] ?? 0;
                if ($user_id > 0) {
                    if (isset($user['derniere_connexion'])) {
                        $pdo->prepare("UPDATE utilisateurs SET derniere_connexion = NOW() WHERE $id_field = ?")
                            ->execute([$user_id]);
                    } elseif (isset($user['last_login'])) {
                        $pdo->prepare("UPDATE utilisateurs SET last_login = NOW() WHERE $id_field = ?")
                            ->execute([$user_id]);
                    }
                }

                header($_SESSION['is_admin'] === 1 ? 'Location: admin_dashboard.php' : 'Location: dashboard.php');
                exit;
            } else {
                $error = "Mot de passe incorrect (compte staff).";
            }
        } else {
            // 2. Tentative membre
            $stmt = $pdo->prepare("SELECT * FROM membres WHERE (email = ? OR numero_membre = ?) AND statut = 'actif'");
            $stmt->execute([$username, $username]);
            $membre = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($membre) {
                $membre_password_hash = $membre['password_hash'] ?? $membre['password'] ?? null;

                if ($membre_password_hash && password_verify($password, $membre_password_hash)) {
                    $_SESSION['user_id']    = $membre['id'] ?? $membre['membre_id'] ?? 0;
                    $_SESSION['membre_id']  = $_SESSION['user_id'];
                    $_SESSION['nom']        = $membre['nom'] ?? '';
                    $_SESSION['prenom']     = $membre['prenom'] ?? '';
                    $_SESSION['email']      = $membre['email'] ?? '';
                    $_SESSION['role']       = 'membre';
                    $_SESSION['type']       = 'membre';
                    $_SESSION['is_admin']   = 0;

                    error_log("Login MEMBRE réussi - email/num: $username");

                    header('Location: index.php');
                    exit;
                } else {
                    $error = "Mot de passe incorrect (compte membre).";
                }
            } else {
                $error = "Identifiants incorrects ou compte inactif.";
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
    <title>Connexion - Bibliotech</title>
     <link rel="icon" type="image/png" href="favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="favicon.svg" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png" />
    <link rel="manifest" href="site.webmanifest" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --primary-dark: #152a45;
            --accent: #e8b86d;
            --accent-hover: #d4a55c;
            --secondary: #3d7a8c;
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
            --gray-800: #1e293b;
            --radius: 16px;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
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
            overflow-x: hidden; /* Empêche le scroll horizontal seulement */
            overflow-y: auto;   /* Permet le scroll vertical si besoin */
        }

        /* Background Pattern - same as index hero */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255,255,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(255,255,255,0.05) 0%, transparent 50%);
            pointer-events: none;
        }

        .login-container {
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: white;
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: var(--shadow-xl);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        .login-header {
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
            box-shadow: var(--shadow-lg);
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

        /* Alert */
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .alert-danger {
            background: var(--error-light);
            color: var(--error);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Form Styles */
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
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            color: var(--dark);
            background: white;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(30, 58, 95, 0.1);
        }

        .form-control:focus + .input-icon,
        .input-wrapper:focus-within .input-icon {
            color: var(--primary);
        }

        .form-control::placeholder {
            color: var(--gray-400);
        }

        /* Button Styles */
        .btn {
            width: 100%;
            padding: 16px 24px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(30, 58, 95, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(30, 58, 95, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--gray-200);
        }

        .btn-secondary:hover {
            border-color: var(--primary);
            background: var(--gray-50);
        }

        /* Links */
        .form-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            margin-bottom: 24px;
        }

        .form-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .form-link:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            margin: 32px 0;
            color: var(--gray-400);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--gray-200);
        }

        .divider span {
            padding: 0 16px;
        }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 24px;
        }

        .back-link {
            color: var(--gray-500);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: var(--primary);
            gap: 12px;
        }

        /* Floating decorative elements */
        .floating-shape {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            pointer-events: none;
        }

        .shape-1 {
            width: 300px;
            height: 300px;
            top: -150px;
            right: -100px;
        }

        .shape-2 {
            width: 200px;
            height: 200px;
            bottom: -100px;
            left: -50px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 32px 24px;
            }

            h1 {
                font-size: 1.5rem;
            }

            .logo-wrapper {
                width: 64px;
                height: 64px;
            }

            .logo-icon {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

    <!-- Decorative background shapes -->
    <div class="floating-shape shape-1"></div>
    <div class="floating-shape shape-2"></div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-wrapper">
                    <i class="fas fa-book-reader logo-icon"></i>
                </div>
                <h1>Connexion</h1>
                <p class="subtitle">Accédez à votre espace Bibliotech</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="form-group">
                    <label for="username" class="form-label">Nom d'utilisateur ou Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" 
                               class="form-control" 
                               id="username" 
                               name="username"
                               placeholder="Entrez votre identifiant"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                               required 
                               autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Mot de passe</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password"
                               placeholder="Entrez votre mot de passe"
                               required>
                    </div>
                </div>

                <div class="form-links">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.875rem; color: var(--gray-600);">
                        <input type="checkbox" style="width: 16px; height: 16px; accent-color: var(--primary);">
                        Se souvenir de moi
                    </label>
                    <a href="./forgot_password.php" class="form-link">Mot de passe oublié ?</a>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    Se connecter
                </button>

                <div class="divider">
                    <span>ou</span>
                </div>

                <a href="inscription_membre.php" class="btn btn-secondary">
                    <i class="fas fa-user-plus"></i>
                    Créer un compte membre
                </a>
            </form>

            <div class="login-footer">
                <a href="index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Retour à l'accueil
                </a>
            </div>
        </div>
    </div>

</body>
</html>