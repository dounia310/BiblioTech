<?php
session_start();
require_once 'connexion.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'membre') {
    header("Location: index.php");
    exit();
}

$membre_id = $_SESSION['user_id'];
$message = '';
$message_type = ''; // success, error, warning

// Récupérer les infos du membre
$sql = "SELECT * FROM membres WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$membre_id]);
$membre = $stmt->fetch();

if (!$membre) {
    header("Location: index.php");
    exit();
}

// Traitement mise à jour profil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $telephone = trim($_POST['telephone']);
    $adresse = trim($_POST['adresse']);
    $ville = trim($_POST['ville']);
    $code_postal = trim($_POST['code_postal']);
    $pays = trim($_POST['pays']);
    $date_naissance = $_POST['date_naissance'];
    $profession = trim($_POST['profession']);
    $institution = trim($_POST['institution']);
    
    // Validation des champs obligatoires
    if (empty($nom) || empty($prenom)) {
        $message = "Le nom et le prénom sont obligatoires";
        $message_type = 'error';
    } else {
        // Gestion photo de profil
        $photo_url = $membre['photo_url'];
        if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] == 0) {
            $file = $_FILES['photo_profil'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($ext, $allowed)) {
                if ($file['size'] < 5000000) { // 5MB
                    $filename = 'profil_' . $membre_id . '_' . time() . '.' . $ext;
                    $upload_dir = 'uploads/';
                    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                        // Supprimer l'ancienne photo si elle existe
                        if ($photo_url && file_exists($photo_url) && $photo_url != 'uploads/default-avatar.png') {
                            unlink($photo_url);
                        }
                        $photo_url = $upload_dir . $filename;
                    } else {
                        $message = "Erreur lors du téléchargement de la photo";
                        $message_type = 'error';
                    }
                } else {
                    $message = "La photo est trop volumineuse (max 5MB)";
                    $message_type = 'error';
                }
            } else {
                $message = "Format de photo non supporté (JPG, PNG, GIF uniquement)";
                $message_type = 'error';
            }
        }
        
        // Gestion photo de couverture
        $carte_url = $membre['carte_url'];
        if (isset($_FILES['photo_couverture']) && $_FILES['photo_couverture']['error'] == 0) {
            $file = $_FILES['photo_couverture'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($ext, $allowed)) {
                if ($file['size'] < 5000000) {
                    $filename = 'couverture_' . $membre_id . '_' . time() . '.' . $ext;
                    $upload_dir = 'uploads/';
                    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                        // Supprimer l'ancienne couverture si elle existe
                        if ($carte_url && file_exists($carte_url) && $carte_url != 'uploads/default-cover.jpg') {
                            unlink($carte_url);
                        }
                        $carte_url = $upload_dir . $filename;
                    }
                }
            }
        }
        
        if ($message_type != 'error') {
            try {
                $sql = "UPDATE membres SET 
                        nom = ?, prenom = ?, telephone = ?, adresse = ?,
                        ville = ?, code_postal = ?, pays = ?, date_naissance = ?,
                        profession = ?, institution = ?, photo_url = ?, carte_url = ?,
                        updated_at = NOW()
                        WHERE id = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $nom, $prenom, $telephone, $adresse, $ville, $code_postal,
                    $pays, $date_naissance, $profession, $institution, 
                    $photo_url, $carte_url, $membre_id
                ]);
                
                $message = "Profil mis à jour avec succès !";
                $message_type = 'success';
                
                // Recharger les données
                $stmt = $pdo->prepare("SELECT * FROM membres WHERE id = ?");
                $stmt->execute([$membre_id]);
                $membre = $stmt->fetch();
                
            } catch (PDOException $e) {
                $message = "Erreur lors de la mise à jour : " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Changer mot de passe
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    if (password_verify($current, $membre['password_hash'])) {
        if (strlen($new) < 8) {
            $message = "Le mot de passe doit contenir au moins 8 caractères";
            $message_type = 'error';
        } elseif ($new === $confirm) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $sql = "UPDATE membres SET password_hash = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$hash, $membre_id]);
            $message = "Mot de passe changé avec succès !";
            $message_type = 'success';
        } else {
            $message = "Les mots de passe ne correspondent pas";
            $message_type = 'error';
        }
    } else {
        $message = "Ancien mot de passe incorrect";
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier mon profil - Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    /* Variables CSS avec vos couleurs */
    :root {
        --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --primary-lighter: #152a45;
            --success: #10b981;
        --secondary: #7ab781;
        --secondary-light: #8cc793;
        --secondary-lighter: #e9f5ea;
        --accent: #254c5b;
        --accent-light: #2f5e71;
        --accent-lighter: #e3eaed;
        --white: #ffffff;
        --black: #121212;
        --gray-50: #fafafa;
        --gray-100: #f5f5f5;
        --gray-200: #eeeeee;
        --gray-300: #e0e0e0;
        --gray-400: #bdbdbd;
        --gray-500: #9e9e9e;
        --gray-600: #757575;
        --gray-700: #616161;
        --gray-800: #424242;
        --gray-900: #212121;
        --success: #4caf50;
        --warning: #ff9800;
        --danger: #f44336;
        --info: #2196f3;
        --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
        --shadow: 0 4px 8px rgba(0,0,0,0.15);
        --shadow-lg: 0 10px 20px rgba(0,0,0,0.2);
        --shadow-xl: 0 15px 30px rgba(0,0,0,0.25);
        --border-radius-sm: 8px;
        --border-radius: 12px;
        --border-radius-lg: 16px;
        --border-radius-xl: 24px;
        --border-radius-full: 9999px;
        --transition: all 0.3s ease;
        --font-sans: 'Segoe UI', system-ui, -apple-system, sans-serif;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html {
        font-size: 16px;
    }

    body {
        font-family: var(--font-sans);
        background: linear-gradient(135deg, var(--gray-50) 0%, var(--primary-lighter) 100%);
        color: var(--gray-800);
        line-height: 1.6;
        min-height: 100vh;
    }

    /* Header */
    .main-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
        color: var(--white);
        padding: 1.5rem 2rem;
        box-shadow: var(--shadow-lg);
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .header-container {
        max-width: 1400px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1.5rem;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        color: var(--white);
        font-weight: 800;
        font-size: 1.8rem;
        transition: var(--transition);
    }

    .logo:hover {
        transform: translateY(-2px);
    }

    .logo-icon {
        width: 40px;
        height: 40px;
        background: var(--secondary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: var(--shadow);
    }

    .nav-menu {
        display: flex;
        gap: 1rem;
        list-style: none;
    }

    .nav-link {
        color: var(--white);
        text-decoration: none;
        padding: 0.75rem 1.25rem;
        border-radius: var(--border-radius-full);
        transition: var(--transition);
        font-weight: 600;
        background: rgba(255, 255, 255, 0.1);
    }

    .nav-link:hover, .nav-link.active {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
    }

    /* Main Content */
    .main-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 0 1.5rem;
    }

    .page-header {
        text-align: center;
        margin-bottom: 3rem;
        padding: 2rem;
        background: var(--white);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-lg);
        border-left: 5px solid var(--primary);
    }

    .page-title {
        font-size: 2.5rem;
        color: var(--accent);
        margin-bottom: 0.5rem;
        font-weight: 800;
    }

    .page-subtitle {
        color: var(--gray-600);
        font-size: 1.1rem;
    }

    /* Messages */
    .message {
        padding: 1rem 1.5rem;
        border-radius: var(--border-radius);
        margin: 1.5rem 0;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        animation: slideIn 0.3s ease-out;
    }

    .message.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .message.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .message.warning {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Profile Container */
    .profile-container {
        display: grid;
        grid-template-columns: 1fr;
        gap: 2rem;
        margin-bottom: 3rem;
    }

    /* Photos Section */
    .photos-section {
        background: var(--white);
        border-radius: var(--border-radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--gray-200);
    }

    .section-title {
        font-size: 1.5rem;
        color: var(--accent);
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--primary-lighter);
        font-weight: 700;
    }

    .photos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-bottom: 1.5rem;
    }

    .photo-upload {
        text-align: center;
    }

    .photo-preview {
        width: 200px;
        height: 200px;
        margin: 0 auto 1rem;
        border-radius: var(--border-radius-lg);
        overflow: hidden;
        background: var(--gray-100);
        border: 3px solid var(--gray-300);
        transition: var(--transition);
    }

    .photo-preview:hover {
        transform: scale(1.05);
        border-color: var(--primary);
    }

    .photo-preview.profile {
        border-radius: 50%;
    }

    .photo-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .photo-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: var(--gray-500);
        background: var(--gray-100);
    }

    .photo-placeholder i {
        font-size: 3rem;
        margin-bottom: 0.5rem;
        opacity: 0.7;
    }

    .file-input-container {
        position: relative;
        display: inline-block;
        margin-top: 1rem;
    }

    .file-input {
        position: absolute;
        left: 0;
        top: 0;
        opacity: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
    }

    .file-input-label {
        display: inline-block;
        padding: 0.75rem 1.5rem;
        background: var(--primary);
        color: var(--white);
        border-radius: var(--border-radius);
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
    }

    .file-input-label:hover {
        background: var(--primary-light);
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .file-info {
        font-size: 0.85rem;
        color: var(--gray-600);
        margin-top: 0.5rem;
    }

    /* Forms */
    .form-section {
        background: var(--white);
        border-radius: var(--border-radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--gray-200);
        margin-bottom: 2rem;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .form-group {
        margin-bottom: 1.25rem;
    }

    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: var(--accent);
        font-size: 0.95rem;
    }

    .form-label.required::after {
        content: " *";
        color: var(--danger);
    }

    .form-control {
        width: 100%;
        padding: 0.875rem 1rem;
        border: 2px solid var(--gray-300);
        border-radius: var(--border-radius);
        font-size: 1rem;
        transition: var(--transition);
        background: var(--white);
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(43, 132, 142, 0.1);
    }

    .form-control:read-only, .form-control:disabled {
        background: var(--gray-100);
        color: var(--gray-600);
        cursor: not-allowed;
    }

    .form-help {
        font-size: 0.85rem;
        color: var(--gray-600);
        margin-top: 0.25rem;
    }

    /* Buttons */
    .btn {
        padding: 0.875rem 2rem;
        border: none;
        border-radius: var(--border-radius);
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        text-decoration: none;
    }

    .btn-primary {
        background: var(--primary);
        color: var(--white);
    }

    .btn-primary:hover {
        background: var(--primary-light);
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .btn-secondary {
        background: var(--gray-200);
        color: var(--gray-800);
    }

    .btn-secondary:hover {
        background: var(--gray-300);
        transform: translateY(-2px);
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 1px solid var(--gray-200);
        flex-wrap: wrap;
    }

    /* Password Section */
    .password-section {
        background: var(--white);
        border-radius: var(--border-radius-lg);
        padding: 2rem;
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--gray-200);
    }

    .password-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
        max-width: 500px;
        margin: 0 auto;
    }

    /* Footer */
    .main-footer {
        background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
        color: var(--white);
        text-align: center;
        padding: 2rem;
        margin-top: 4rem;
    }

    .footer-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1.5rem;
    }

    .footer-links {
        display: flex;
        justify-content: center;
        gap: 2rem;
        margin: 1.5rem 0;
        flex-wrap: wrap;
    }

    .footer-links a {
        color: var(--secondary);
        text-decoration: none;
        font-weight: 600;
        transition: var(--transition);
    }

    .footer-links a:hover {
        color: var(--white);
        text-shadow: 0 0 10px var(--secondary);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .main-header {
            padding: 1rem;
        }
        
        .header-container {
            flex-direction: column;
            text-align: center;
        }
        
        .nav-menu {
            justify-content: center;
            width: 100%;
        }
        
        .main-container {
            padding: 0 1rem;
        }
        
        .page-title {
            font-size: 2rem;
        }
        
        .photos-grid {
            grid-template-columns: 1fr;
        }
        
        .photo-preview {
            width: 150px;
            height: 150px;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .btn {
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .page-title {
            font-size: 1.75rem;
        }
        
        .photos-section,
        .form-section,
        .password-section {
            padding: 1.5rem;
        }
        
        .photo-preview {
            width: 120px;
            height: 120px;
        }
    }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <div class="header-container">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-book"></i>
                </div>
                <span>Bibliotech</span>
            </a>
            
            <nav>
                <ul class="nav-menu">
                    <li><a href="index.php" class="nav-link"><i class="fas fa-home"></i> Accueil</a></li>
                    <li><a href="catalogue.php" class="nav-link"><i class="fas fa-book-open"></i> Catalogue</a></li>
                    <li><a href="recherche.php" class="nav-link"><i class="fas fa-search"></i> Recherche</a></li>
                    <li><a href="mon_compte.php" class="nav-link active"><i class="fas fa-user"></i> Mon compte</a></li>
                    <li><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-container">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-user-edit"></i> Modifier mon profil</h1>
            <p class="page-subtitle">Mettez à jour vos informations personnelles et votre mot de passe</p>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <i class="fas fa-<?php 
                    echo $message_type == 'success' ? 'check-circle' : 
                           ($message_type == 'error' ? 'exclamation-circle' : 'exclamation-triangle'); 
                ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="profile-container">
            <!-- Photos Section -->
            <section class="photos-section">
                <h2 class="section-title"><i class="fas fa-images"></i> Photos</h2>
                <div class="photos-grid">
                    <div class="photo-upload">
                        <div class="photo-preview profile">
                            <?php if (!empty($membre['photo_url']) && file_exists($membre['photo_url'])): ?>
                                <img src="<?php echo htmlspecialchars($membre['photo_url']); ?>" alt="Photo de profil">
                            <?php else: ?>
                                <div class="photo-placeholder">
                                    <i class="fas fa-user"></i>
                                    <span>Pas de photo</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="file-input-container">
                            <input type="file" name="photo_profil" id="photo_profil" class="file-input" accept="image/*">
                            <label for="photo_profil" class="file-input-label">
                                <i class="fas fa-camera"></i> Changer la photo
                            </label>
                        </div>
                        <div class="file-info">Photo de profil (max 5MB)</div>
                    </div>

                    <div class="photo-upload">
                        <div class="photo-preview">
                            <?php if (!empty($membre['carte_url']) && file_exists($membre['carte_url'])): ?>
                                <img src="<?php echo htmlspecialchars($membre['carte_url']); ?>" alt="Photo de couverture">
                            <?php else: ?>
                                <div class="photo-placeholder">
                                    <i class="fas fa-image"></i>
                                    <span>Pas de photo</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="file-input-container">
                            <input type="file" name="photo_couverture" id="photo_couverture" class="file-input" accept="image/*">
                            <label for="photo_couverture" class="file-input-label">
                                <i class="fas fa-image"></i> Changer la photo
                            </label>
                        </div>
                        <div class="file-info">Photo de couverture (max 5MB)</div>
                    </div>
                </div>
                <p class="form-help">Formats acceptés : JPG, PNG, GIF. Taille maximale : 5MB.</p>
            </section>

            <!-- Informations personnelles -->
            <form method="POST" action="" enctype="multipart/form-data" class="form-section">
                <h2 class="section-title"><i class="fas fa-user-circle"></i> Informations personnelles</h2>
                
                <div class="form-grid">
                    <!-- Informations non modifiables -->
                    <div class="form-group">
                        <label class="form-label">Numéro de membre</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($membre['numero_membre']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($membre['email']); ?>" readonly>
                        <div class="form-help">Non modifiable</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Nom</label>
                        <input type="text" name="nom" class="form-control" value="<?php echo htmlspecialchars($membre['nom']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Prénom</label>
                        <input type="text" name="prenom" class="form-control" value="<?php echo htmlspecialchars($membre['prenom']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Téléphone</label>
                        <input type="tel" name="telephone" class="form-control" value="<?php echo htmlspecialchars($membre['telephone']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date de naissance</label>
                        <input type="date" name="date_naissance" class="form-control" value="<?php echo htmlspecialchars($membre['date_naissance']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Adresse</label>
                        <input type="text" name="adresse" class="form-control" value="<?php echo htmlspecialchars($membre['adresse']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ville</label>
                        <input type="text" name="ville" class="form-control" value="<?php echo htmlspecialchars($membre['ville']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Code postal</label>
                        <input type="text" name="code_postal" class="form-control" value="<?php echo htmlspecialchars($membre['code_postal']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Pays</label>
                        <input type="text" name="pays" class="form-control" value="<?php echo htmlspecialchars($membre['pays']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Profession</label>
                        <input type="text" name="profession" class="form-control" value="<?php echo htmlspecialchars($membre['profession']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Institution</label>
                        <input type="text" name="institution" class="form-control" value="<?php echo htmlspecialchars($membre['institution']); ?>">
                    </div>

                    <!-- Informations système -->
                    <div class="form-group">
                        <label class="form-label">Type de membre</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($membre['type_membre']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Statut</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($membre['statut']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date d'inscription</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($membre['date_inscription']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date d'expiration</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($membre['date_expiration']); ?>" readonly>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                    <a href="membre_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Retour à mon espace
                    </a>
                </div>
            </form>

            <!-- Changement de mot de passe -->
            <form method="POST" action="" class="password-section">
                <h2 class="section-title"><i class="fas fa-lock"></i> Changer mon mot de passe</h2>
                
                <div class="password-grid">
                    <div class="form-group">
                        <label class="form-label required">Ancien mot de passe</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Nouveau mot de passe</label>
                        <input type="password" name="new_password" class="form-control" required minlength="8">
                        <div class="form-help">Minimum 8 caractères</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Confirmer le nouveau mot de passe</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="8">
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-key"></i> Changer le mot de passe
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="footer-content">
            <p>Bibliotech &copy; <?php echo date('Y'); ?> - Votre bibliothèque numérique</p>
            <div class="footer-links">
                <a href="index.php"><i class="fas fa-home"></i> Accueil</a>
                <a href="catalogue.php"><i class="fas fa-book-open"></i> Catalogue</a>
                <a href="contact.php"><i class="fas fa-envelope"></i> Contact</a>
                <a href="aide.php"><i class="fas fa-question-circle"></i> Aide</a>
            </div>
        </div>
    </footer>

    <script>
    // Prévisualisation des photos
    const photoProfilInput = document.getElementById('photo_profil');
    const photoCouvertureInput = document.getElementById('photo_couverture');
    
    function previewImage(input, previewClass) {
        const file = input.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.querySelector(previewClass);
                const img = preview.querySelector('img');
                const placeholder = preview.querySelector('.photo-placeholder');
                
                if (img) {
                    img.src = e.target.result;
                } else if (placeholder) {
                    placeholder.innerHTML = `<img src="${e.target.result}" alt="Prévisualisation">`;
                } else {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Prévisualisation">`;
                }
            }
            reader.readAsDataURL(file);
        }
    }
    
    if (photoProfilInput) {
        photoProfilInput.addEventListener('change', function() {
            previewImage(this, '.photo-preview.profile');
        });
    }
    
    if (photoCouvertureInput) {
        photoCouvertureInput.addEventListener('change', function() {
            previewImage(this, '.photo-preview:not(.profile)');
        });
    }
    
    // Validation du formulaire
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                // Validation des mots de passe
                if (form.querySelector('[name="new_password"]') && form.querySelector('[name="confirm_password"]')) {
                    const newPassword = form.querySelector('[name="new_password"]').value;
                    const confirmPassword = form.querySelector('[name="confirm_password"]').value;
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('Les mots de passe ne correspondent pas.');
                        return false;
                    }
                    
                    if (newPassword.length < 8) {
                        e.preventDefault();
                        alert('Le mot de passe doit contenir au moins 8 caractères.');
                        return false;
                    }
                }
                
                // Validation des champs requis
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                let firstInvalidField = null;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        if (!firstInvalidField) firstInvalidField = field;
                        field.style.borderColor = '#f44336';
                    } else {
                        field.style.borderColor = '';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    if (firstInvalidField) {
                        firstInvalidField.focus();
                        alert('Veuillez remplir tous les champs obligatoires (*).');
                    }
                    return false;
                }
                
                return true;
            });
        });
        
        // Auto-suppression des messages après 5 secondes
        const message = document.querySelector('.message');
        if (message) {
            setTimeout(() => {
                message.style.opacity = '0';
                message.style.transform = 'translateY(-10px)';
                setTimeout(() => message.remove(), 300);
            }, 5000);
        }
    });
    </script>
</body>
</html>