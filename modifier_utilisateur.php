<?php
require_once 'connexion.php';
session_start();

// Protection : uniquement admin ou super_admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    header('Location: login.php');
    exit;
}

$user_id_to_edit = (int)($_GET['id'] ?? 0);
if ($user_id_to_edit <= 0) {
    header('Location: liste_utilisateurs.php?message=' . urlencode('ID utilisateur invalide'));
    exit;
}

// Récupération des données de l'utilisateur à modifier
$stmt = $pdo->prepare("
    SELECT id, nom, prenom, email, telephone, role, statut
    FROM utilisateurs 
    WHERE id = ?
");
$stmt->execute([$user_id_to_edit]);
$user_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_to_edit) {
    header('Location: liste_utilisateurs.php?message=' . urlencode('Utilisateur introuvable'));
    exit;
}

// Notifications (comme partout)
$stmt_notif = $pdo->query("
    SELECT id, type, titre, message, lien, lue, created_at 
    FROM notifications 
    ORDER BY created_at DESC 
    LIMIT 8
");
$notifications = $stmt_notif->fetchAll(PDO::FETCH_ASSOC) ?? [];

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $role = $_POST['role'] ?? $user_to_edit['role'];
    $statut = $_POST['statut'] ?? $user_to_edit['statut'];

    if (empty($nom) || empty($prenom) || empty($email)) {
        $error = "Les champs Nom, Prénom et Email sont obligatoires.";
    } else {
        try {
            // Vérifier si l'email est déjà utilisé par un autre utilisateur
            $check = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
            $check->execute([$email, $user_id_to_edit]);
            if ($check->fetch()) {
                $error = "Cet email est déjà utilisé par un autre utilisateur.";
            } else {
                $upd = $pdo->prepare("
                    UPDATE utilisateurs 
                    SET nom = ?, prenom = ?, email = ?, telephone = ?, role = ?, statut = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $upd->execute([$nom, $prenom, $email, $telephone, $role, $statut, $user_id_to_edit]);

                $success = "Utilisateur modifié avec succès !";
                // Recharger les données
                $user_to_edit = array_merge($user_to_edit, compact('nom', 'prenom', 'email', 'telephone', 'role', 'statut'));
            }
        } catch (PDOException $e) {
            $error = "Erreur lors de la modification : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Utilisateur - BiblioTech</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-700: #334155;
            --light: #ffffff;
            --danger: #dc2626;
            --success: #10b981;
            --radius: 10px;
        }

        body { font-family: 'Inter', sans-serif; background: var(--gray-100); color: var(--gray-700); margin: 0; min-height: 100vh; }

        .admin-header {
            background: var(--light);
            border-bottom: 1px solid var(--gray-200);
            padding: 16px 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-link { color: var(--primary); font-weight: 600; text-decoration: none; }

        .main-content {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .content-section {
            background: var(--light);
            border-radius: var(--radius);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, var(--primary) 0%, #2d5a87 100%);
            color: white;
            padding: 20px 30px;
        }

        .section-title {
            margin: 0;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-container {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: var(--radius);
            font-size: 1rem;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-light); }

        .alert-success {
            background: rgba(16,185,129,0.1);
            color: var(--success);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .alert-danger {
            background: rgba(220,38,38,0.1);
            color: var(--danger);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            margin-top: 2rem;
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
            <div class="user-profile">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></strong>
                    <span>Administrateur</span>
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
                    <i class="fas fa-user-edit"></i>
                    Modifier l'utilisateur
                </div>
            </div>

            <div class="form-container">
                <?php if ($success): ?>
                    <div class="alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($user_to_edit['nom']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="prenom">Prénom</label>
                        <input type="text" id="prenom" name="prenom" value="<?= htmlspecialchars($user_to_edit['prenom']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user_to_edit['email']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone" value="<?= htmlspecialchars($user_to_edit['telephone'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="role">Rôle</label>
                        <select id="role" name="role" required>
                            <option value="bibliothecaire" <?= $user_to_edit['role'] === 'bibliothecaire' ? 'selected' : '' ?>>Bibliothécaire</option>
                            <option value="admin" <?= $user_to_edit['role'] === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                            <option value="super_admin" <?= $user_to_edit['role'] === 'super_admin' ? 'selected' : '' ?>>Super Administrateur</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="statut">Statut</label>
                        <select id="statut" name="statut" required>
                            <option value="actif" <?= $user_to_edit['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                            <option value="inactif" <?= $user_to_edit['statut'] === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                            <option value="suspendu" <?= $user_to_edit['statut'] === 'suspendu' ? 'selected' : '' ?>>Suspendu</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                </form>

                <a href="liste_utilisateurs.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Retour à la liste
                </a>
            </div>
        </section>

    </div>

</body>
</html>