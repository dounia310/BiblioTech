<?php
session_start();
require_once 'connexion.php';

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Messages
$success = '';
$error = '';

// Mettre à jour les informations personnelles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $email = $_POST['email'];
    $telephone = $_POST['telephone'];
    $adresse = $_POST['adresse'];
    
    try {
        $stmt = $pdo->prepare("UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, telephone = ?, adresse = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$nom, $prenom, $email, $telephone, $adresse, $user_id]);
        
        // Mettre à jour la session
        $_SESSION['nom'] = $nom;
        $_SESSION['prenom'] = $prenom;
        $_SESSION['email'] = $email;
        
        $success = "Profil mis à jour avec succès!";
        
        // Recharger les données
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Changer le mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Vérifier le mot de passe actuel
    if (!password_verify($current_password, $user['password'])) {
        $error = "Mot de passe actuel incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Les nouveaux mots de passe ne correspondent pas.";
    } elseif (strlen($new_password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE utilisateurs SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $success = "Mot de passe changé avec succès!";
            
        } catch (PDOException $e) {
            $error = "Erreur: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Bibliotech</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <div class="section-card">
            <div class="section-header">
                <h1 class="section-title">
                    <i class="fas fa-cog"></i> Paramètres du compte
                </h1>
            </div>
            
            <div class="card-body" style="padding: 2rem;">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= $success ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    </div>
                <?php endif; ?>
                
                <div class="settings-tabs">
                    <div class="tab-header">
                        <button class="tab-btn active" onclick="openTab(event, 'profile')">
                            <i class="fas fa-user-circle"></i> Profil
                        </button>
                        <button class="tab-btn" onclick="openTab(event, 'password')">
                            <i class="fas fa-lock"></i> Sécurité
                        </button>
                        <button class="tab-btn" onclick="openTab(event, 'preferences')">
                            <i class="fas fa-sliders-h"></i> Préférences
                        </button>
                    </div>
                    
                    <div id="profile" class="tab-content active">
                        <h3><i class="fas fa-user-edit"></i> Modifier vos informations</h3>
                        <form method="POST">
                            <input type="hidden" name="update_profile" value="1">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="nom">Nom</label>
                                    <input type="text" id="nom" name="nom" class="form-control" 
                                           value="<?= htmlspecialchars($user['nom']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="prenom">Prénom</label>
                                    <input type="text" id="prenom" name="prenom" class="form-control" 
                                           value="<?= htmlspecialchars($user['prenom']) ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="telephone">Téléphone</label>
                                <input type="tel" id="telephone" name="telephone" class="form-control" 
                                       value="<?= htmlspecialchars($user['telephone']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="adresse">Adresse</label>
                                <textarea id="adresse" name="adresse" class="form-control" rows="3"><?= htmlspecialchars($user['adresse']) ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les modifications
                            </button>
                        </form>
                    </div>
                    
                    <div id="password" class="tab-content">
                        <h3><i class="fas fa-key"></i> Changer le mot de passe</h3>
                        <form method="POST">
                            <input type="hidden" name="change_password" value="1">
                            <div class="form-group">
                                <label for="current_password">Mot de passe actuel</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_password">Nouveau mot de passe</label>
                                    <input type="password" id="new_password" name="new_password" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                </div>
                            </div>
                            <div class="password-strength">
                                <p>Le mot de passe doit contenir au moins:</p>
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
                    
                    <div id="preferences" class="tab-content">
                        <h3><i class="fas fa-bell"></i> Préférences de notification</h3>
                        <form method="POST" action="update_preferences.php">
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <label>
                                        <input type="checkbox" name="email_notifications" checked>
                                        <span>Notifications par email</span>
                                    </label>
                                    <small>Recevoir des emails pour les retards et nouvelles disponibilités</small>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <label>
                                        <input type="checkbox" name="sms_notifications">
                                        <span>Notifications par SMS</span>
                                    </label>
                                    <small>Recevoir des SMS pour les retards importants</small>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="language">Langue préférée</label>
                                <select id="language" name="language" class="form-control">
                                    <option value="fr" selected>Français</option>
                                    <option value="en">English</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="results_per_page">Résultats par page</label>
                                <select id="results_per_page" name="results_per_page" class="form-control">
                                    <option value="10">10</option>
                                    <option value="20" selected>20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les préférences
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="section-card">
            <div class="section-header">
                <h3 class="section-title">
                    <i class="fas fa-chart-bar"></i> Statistiques de votre compte
                </h3>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-book"></i>
                        <h3>
                            <?php
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM emprunts WHERE utilisateur_id = ?");
                            $stmt->execute([$user_id]);
                            echo $stmt->fetchColumn();
                            ?>
                        </h3>
                        <p>Emprunts totaux</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-clock"></i>
                        <h3>
                            <?php
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM emprunts WHERE utilisateur_id = ? AND date_retour IS NULL");
                            $stmt->execute([$user_id]);
                            echo $stmt->fetchColumn();
                            ?>
                        </h3>
                        <p>Emprunts en cours</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-bookmark"></i>
                        <h3>
                            <?php
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE utilisateur_id = ? AND statut = 'en_attente'");
                            $stmt->execute([$user_id]);
                            echo $stmt->fetchColumn();
                            ?>
                        </h3>
                        <p>Réservations actives</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-calendar-check"></i>
                        <h3>
                            <?php
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM emprunts WHERE utilisateur_id = ? AND date_retour < date_retour_prevue");
                            $stmt->execute([$user_id]);
                            echo $stmt->fetchColumn();
                            ?>
                        </h3>
                        <p>Retours anticipés</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            
            // Masquer tous les contenus d'onglets
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            
            // Désactiver tous les boutons d'onglets
            tablinks = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            
            // Afficher l'onglet actuel
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
    </script>
</body>
</html>