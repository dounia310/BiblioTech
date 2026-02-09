<?php
ob_start();
session_start();

require_once 'connexion.php';

$error = '';

// Debug session (optionnel - décommente si besoin pendant test)
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     error_log("POST reçue : " . print_r($_POST, true));
//     error_log("Session avant traitement : " . print_r($_SESSION, true));
// }

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
                // Connexion staff réussie
                $_SESSION['user_id']   = $user['id'] ?? $user['user_id'] ?? 0;
                $_SESSION['username']  = $user['username'] ?? $user['email'] ?? '';
                $_SESSION['nom']       = $user['nom'] ?? '';
                $_SESSION['prenom']    = $user['prenom'] ?? '';
                $_SESSION['role']      = $user['role'] ?? 'bibliothecaire';
                $_SESSION['type']      = 'staff';

                // Calcul is_admin robuste (insensible à la casse)
                $role_lower = strtolower(trim($_SESSION['role']));
                $is_admin = in_array($role_lower, ['admin', 'super_admin', 'administrateur', 'superadmin']) ||
                            strtolower($_SESSION['username']) === 'superadmin';

                $_SESSION['is_admin'] = $is_admin ? 1 : 0;

                // Debug login (à commenter après test)
                error_log("Login STAFF réussi - username: {$_SESSION['username']} | role: {$_SESSION['role']} | is_admin: {$_SESSION['is_admin']}");

                // Mise à jour dernière connexion
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

                // Redirection
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
                    // Connexion membre réussie
                    $_SESSION['user_id']    = $membre['id'] ?? $membre['membre_id'] ?? 0;
                    $_SESSION['membre_id']  = $_SESSION['user_id'];
                    $_SESSION['nom']        = $membre['nom'] ?? '';
                    $_SESSION['prenom']     = $membre['prenom'] ?? '';
                    $_SESSION['email']      = $membre['email'] ?? '';
                    $_SESSION['role']       = 'membre';
                    $_SESSION['type']       = 'membre';
                    $_SESSION['is_admin']   = 0;  // explicitement non-admin

                    // Debug
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
   :root{--primary:#1e6a72;--primary-dark:#145459;--accent:#fd7e14;--glass-bg:rgba(255,255,255,0.12);--glass-border:rgba(255,255,255,0.18);--text-light:rgba(255,255,255,0.95);--text-muted:rgba(255,255,255,0.75);--radius:20px;--shadow:0 12px 35px rgba(0,0,0,0.25);}*{margin:0;padding:0;box-sizing:border-box;}body{font-family:'Roboto',system-ui,sans-serif;min-height:100vh;background:url('https://i.pinimg.com/1200x/27/b5/90/27b590f352b9d8ed79063df4bd6ddf75.jpg') center/cover no-repeat fixed;color:white;position:relative;display:flex;align-items:center;justify-content:center;padding:20px;}body::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(30,106,114,0.45),rgba(108,117,125,0.35));backdrop-filter:blur(4px);z-index:-1;}.login-wrapper{background:var(--glass-bg);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border:1px solid var(--glass-border);border-radius:var(--radius);box-shadow:var(--shadow);padding:45px 35px;width:100%;max-width:420px;transition:transform 0.35s ease,box-shadow 0.35s ease;}.login-wrapper:hover{transform:translateY(-12px);box-shadow:0 20px 50px rgba(0,0,0,0.35);}.logo-container{text-align:center;margin-bottom:30px;}.logo-icon{font-size:4.2rem;background:linear-gradient(135deg,var(--primary),#4da8af);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:12px;}h2{font-size:2.1rem;font-weight:700;margin-bottom:10px;text-shadow:0 2px 12px rgba(0,0,0,0.4);}.subtitle{color:var(--text-muted);font-size:1.05rem;margin-bottom:35px;}.alert-danger{background:rgba(220,53,69,0.28);border:1px solid rgba(220,53,69,0.45);color:#ffdddd;padding:14px;border-radius:12px;margin-bottom:25px;display:flex;align-items:center;gap:10px;font-size:0.95rem;}.form-label{color:var(--text-light);font-weight:500;margin-bottom:8px;display:block;}.input-group{position:relative;}.input-group-text{background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);color:white;border-radius:12px 0 0 12px;padding:0 16px;}.form-control{background:rgba(255,255,255,0.18);border:1px solid rgba(255,255,255,0.25);color:white;border-radius:0 12px 12px 0;padding:14px 16px;transition:all 0.25s ease;}.form-control:focus{background:rgba(255,255,255,0.25);border-color:var(--primary);box-shadow:0 0 0 4px rgba(30,106,114,0.3);color:white;outline:none;}.form-control::placeholder{color:rgba(255,255,255,0.6);}.btn-primary{background:linear-gradient(90deg,var(--primary),#4da8af);border:none;padding:14px;font-size:1.1rem;font-weight:600;border-radius:12px;transition:all 0.3s ease;box-shadow:0 6px 20px rgba(30,106,114,0.4);color:white;cursor:pointer;}.btn-primary:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(30,106,114,0.55);}.btn-link,.btn-outline-primary{color:#ffd166;text-decoration:none;font-weight:500;}.btn-outline-primary{border-color:rgba(255,255,255,0.4);color:white;background:transparent;transition:all 0.25s;}.btn-outline-primary:hover{background:rgba(255,255,255,0.12);border-color:white;}.text-center a{color:var(--text-light);}.separator{color:var(--text-muted);font-size:0.95rem;position:relative;margin:30px 0;}.separator::before,.separator::after{content:'';position:absolute;top:50%;width:40%;height:1px;background:rgba(255,255,255,0.25);transform:translateY(-50%);}.separator::before{left:0;}.separator::after{right:0;}@media (max-width:480px){.login-wrapper{padding:35px 25px;}h2{font-size:1.8rem;}.logo-icon{font-size:3.5rem;}}
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <i class="fas fa-book-reader logo-icon"></i>
            <h2>Connexion Bibliotech</h2>
        </div>

        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate>
                <!-- Ton formulaire reste identique -->
                <div class="mb-4">
                    <label for="username" class="form-label">Nom d'utilisateur ou Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label">Mot de passe</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>

                <div class="d-grid mb-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i> Se connecter
                    </button>
                </div>

                <!-- Le reste du formulaire inchangé -->
                <div class="text-center">
                    <a href="reset_password.php" class="btn-link">Mot de passe oublié ?</a>
                </div>
                <div class="separator my-4">ou</div>
                <div class="text-center">
                    <p class="mb-2">Nouveau membre ?</p>
                    <a href="inscription_membre.php" class="btn btn-outline-primary">
                        Créer un compte membre
                    </a>
                </div>
                <div class="text-center mt-4">
                    <a href="index.php" class="text-muted small">← Retour à l'accueil</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>