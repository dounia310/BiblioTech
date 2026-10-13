<?php
require_once 'connexion.php';
session_start();

// Protection : uniquement admin, super_admin, bibliothécaire
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin', 'bibliothecaire'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Notifications (comme partout)
$stmt_notif = $pdo->query("
    SELECT id, type, titre, message, lien, lue, created_at 
    FROM notifications 
    ORDER BY created_at DESC 
    LIMIT 8
");
$notifications = $stmt_notif->fetchAll(PDO::FETCH_ASSOC) ?? [];

function getNotificationIcon($type) {
    $icons = [
        'reservation' => 'calendar-check',
        'emprunt'     => 'book-reader',
        'amende'      => 'file-invoice-dollar',
        'retard'      => 'clock',
        'info'        => 'info-circle',
        'success'     => 'check-circle',
        'warning'     => 'exclamation-triangle',
    ];
    return $icons[strtolower($type ?? '')] ?? 'bell';
}

// Infos utilisateur
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Messages
$success = '';
$error = '';

// Mise à jour profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');

    try {
        $upd = $pdo->prepare("
            UPDATE utilisateurs 
            SET nom = ?, prenom = ?, email = ?, telephone = ?, adresse = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $upd->execute([$nom, $prenom, $email, $telephone, $adresse, $user_id]);

        $_SESSION['nom'] = $nom;
        $_SESSION['prenom'] = $prenom;
        $_SESSION['email'] = $email;

        $success = "Profil mis à jour avec succès !";
        $user = array_merge($user, ['nom' => $nom, 'prenom' => $prenom, 'email' => $email]);
    } catch (PDOException $e) {
        $error = "Erreur lors de la mise à jour du profil.";
    }
}

// Changement mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $user['password'] ?? '')) {
        $error = "Mot de passe actuel incorrect.";
    } elseif ($new !== $confirm) {
        $error = "Les nouveaux mots de passe ne correspondent pas.";
    } elseif (strlen($new) < 6 || !preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new)) {
        $error = "Le mot de passe doit contenir au moins 6 caractères, 1 majuscule et 1 chiffre.";
    } else {
        try {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE utilisateurs SET password = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$hashed, $user_id]);
            $success = "Mot de passe changé avec succès !";
        } catch (PDOException $e) {
            $error = "Erreur lors du changement de mot de passe.";
        }
    }
}

// Statistiques admin (globales)
$total_livres = $pdo->query("SELECT COUNT(*) FROM livres")->fetchColumn();
$total_membres = $pdo->query("SELECT COUNT(*) FROM membres")->fetchColumn();
$emprunts_en_cours = $pdo->query("SELECT COUNT(*) FROM emprunts WHERE date_retour_effective IS NULL")->fetchColumn();
$amendes_impayees = $pdo->query("SELECT COUNT(*) FROM amendes WHERE statut IN ('impaye', 'partiel')")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres Administrateur - BiblioTech</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --primary-dark: #152a45;
            --accent: #e8b86d;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --light: #ffffff;
            --danger: #dc2626;
            --warning: #f59e0b;
            --success: #10b981;
            
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --radius: 10px;
            --transition: 250ms ease;
        }

        * { margin:0; padding:0; box-sizing:border-box; }

        html, body {
            height: auto !important;
            min-height: 100vh;
            overflow-y: auto !important;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--gray-100);
            color: var(--gray-700);
            line-height: 1.6;
        }

        .admin-header {
            background: var(--light);
            border-bottom: 1px solid var(--gray-200);
            padding: 16px 30px;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-link {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: var(--radius);
            transition: all var(--transition);
        }

        .dashboard-link:hover {
            background: var(--primary);
            color: white;
        }

        .notifications-bell {
            position: relative;
            width: 44px;
            height: 44px;
            background: var(--gray-100);
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-600);
            font-size: 1.1rem;
            cursor: pointer;
        }

        .notifications-bell:hover {
            background: var(--primary);
            color: white;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: 9999px;
            width: 24px;
            height: 24px;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: var(--gray-100);
            border-radius: 9999px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 9999px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-info strong { font-size: 0.9rem; }
        .user-info span { font-size: 0.75rem; color: var(--gray-500); }

        .logout-link {
            color: var(--danger);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: var(--radius);
            transition: all var(--transition);
        }

        .logout-link:hover { background: rgba(220,38,38,0.1); }

        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .content-section {
            background: var(--light);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .section-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i { color: var(--accent); font-size: 1.4rem; }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-weight: 600;
            color: var(--gray-600);
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 10px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius);
            font-size: 1rem;
        }

        .form-group textarea {
            min-height: 100px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
        }

        .stat-card {
            background: var(--light);
            border-radius: var(--radius);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--primary);
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.95rem;
        }

        .alert-success, .alert-danger {
            padding: 1rem;
            border-radius: var(--radius);
            margin: 1rem 2rem;
            text-align: center;
        }

        .alert-success { background: rgba(16,185,129,0.1); color: var(--success); }
        .alert-danger  { background: rgba(220,38,38,0.1); color: var(--danger); }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            margin: 2rem auto;
            display: block;
            text-align: center;
        }

        .back-link:hover { color: var(--primary-light); }

        .password-rules {
            font-size: 0.9rem;
            color: var(--gray-600);
            margin-top: 0.5rem;
        }

        .password-rules ul {
            margin: 0.5rem 0 0 1.5rem;
            padding: 0;
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="admin-header">
        <div class="admin-header-left">
            <a href="admin_dashboard.php" class="dashboard-link">
                <i class="fas fa-home"></i> Retour au Dashboard
            </a>
        </div>

        <div class="admin-header-right">
            <a href="#" class="notifications-bell" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if (count($notifications)): ?>
                    <span class="notification-badge"><?= count($notifications) ?></span>
                <?php endif; ?>
            </a>

            <div class="user-profile">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></strong>
                    <span><?= ucfirst($_SESSION['role'] ?? 'Administrateur') ?></span>
                </div>
                <a href="logout.php" class="logout-link" title="Déconnexion">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- CONTENU -->
    <div class="main-content">

        <section class="content-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-cog"></i>
                    Paramètres Administrateur
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="form-grid">
                <!-- Profil -->
                <div>
                    <h3><i class="fas fa-user-edit"></i> Modifier vos informations</h3>
                    <form method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="form-group">
                            <label for="nom">Nom</label>
                            <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($user['nom'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="prenom">Prénom</label>
                            <input type="text" id="prenom" name="prenom" value="<?= htmlspecialchars($user['prenom'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="telephone">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone" value="<?= htmlspecialchars($user['telephone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="adresse">Adresse</label>
                            <textarea id="adresse" name="adresse"><?= htmlspecialchars($user['adresse'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                    </form>
                </div>

                <!-- Mot de passe -->
                <div>
                    <h3><i class="fas fa-lock"></i> Changer le mot de passe</h3>
                    <form method="POST">
                        <input type="hidden" name="change_password" value="1">
                        <div class="form-group">
                            <label for="current_password">Mot de passe actuel</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">Nouveau mot de passe</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        <div class="password-rules">
                            Le mot de passe doit contenir au moins :
                            <ul>
                                <li>6 caractères minimum</li>
                                <li>1 majuscule</li>
                                <li>1 chiffre</li>
                            </ul>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Changer le mot de passe
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <!-- Statistiques globales pour admin -->
        <section class="content-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-chart-bar"></i>
                    Statistiques globales
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book"></i></div>
                    <div class="stat-number"><?= number_format($total_livres) ?></div>
                    <div class="stat-label">Livres au catalogue</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?= number_format($total_membres) ?></div>
                    <div class="stat-label">Membres inscrits</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                    <div class="stat-number"><?= number_format($emprunts_en_cours) ?></div>
                    <div class="stat-label">Emprunts en cours</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-coins"></i></div>
                    <div class="stat-number"><?= number_format($amendes_impayees) ?></div>
                    <div class="stat-label">Amendes impayées</div>
                </div>
            </div>
        </section>

        <div style="text-align:center; margin: 3rem 0;">
            <a href="admin_dashboard.php" style="color:var(--primary); font-weight:600; text-decoration:none;">
                <i class="fas fa-arrow-left"></i> Retour au Dashboard
            </a>
        </div>

    </div>

</body>
</html>