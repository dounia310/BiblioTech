<?php
require_once 'connexion.php';
require_once 'header.php';

// Génération / vérification CSRF
if (!isset($_SESSION['csrf_contact'])) {
    $_SESSION['csrf_contact'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_contact'];

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $error = "Erreur de sécurité (CSRF). Veuillez réessayer.";
    } else {
        $nom       = trim($_POST['nom'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $sujet     = trim($_POST['sujet'] ?? '');
        $contenu   = trim($_POST['message'] ?? '');
        $type      = $_POST['type'] ?? 'question';

        // Validation
        if ($nom === '' || $email === '' || $sujet === '' || $contenu === '') {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "L'adresse e-mail n'est pas valide.";
        } elseif (strlen($contenu) < 10) {
            $error = "Votre message est trop court (minimum 10 caractères).";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO contacts (nom, email, telephone, sujet, message, type, statut, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'nouveau', NOW())
                ");
                $stmt->execute([$nom, $email, $telephone ?: null, $sujet, $contenu, $type]);

                // Réinitialisation CSRF après succès
                $_SESSION['csrf_contact'] = bin2hex(random_bytes(32));

                $message = "Votre message a bien été envoyé. Merci ! Nous vous répondrons dans les plus brefs délais.";
            } catch (PDOException $e) {
                error_log("Erreur contact: " . $e->getMessage());
                $error = "Une erreur est survenue lors de l'envoi. Veuillez réessayer plus tard.";
            }
        }
    }
}

// Pré-remplissage si membre connecté
$user = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT nom, prenom, email, telephone FROM membres WHERE id = ?");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // silencieux
    }
}
?>

<style>
    .contact-page {
        --contact-primary: #1e3a5f;
        --contact-primary-light: #2d5a87;
        --contact-accent: #e8b86d;
        --contact-success: #10b981;
        --contact-error: #ef4444;
        --contact-dark: #0f172a;
        --contact-gray-100: #f1f5f9;
        --contact-gray-200: #e2e8f0;
        --contact-gray-500: #64748b;
        --contact-shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        --contact-radius: 12px;
        
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: var(--contact-gray-100);
        color: var(--contact-dark);
        line-height: 1.6;
        padding: 40px 0;
    }

    .contact-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .contact-title {
        font-family: 'Playfair Display', serif;
        font-size: 2.8rem;
        text-align: center;
        margin-bottom: 2rem;
        color: var(--contact-primary);
    }

    .contact-alert {
        padding: 1.2rem;
        border-radius: var(--contact-radius);
        margin-bottom: 2rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .contact-alert.success {
        background: #e8f5e9;
        color: #2e7d32;
        border: 1px solid #c8e6c9;
    }

    .contact-alert.error {
        background: #ffebee;
        color: #c62828;
        border: 1px solid #ef9a9a;
    }

    .contact-form {
        background: white;
        padding: 2.5rem;
        border-radius: var(--contact-radius);
        box-shadow: var(--contact-shadow-md);
    }

    .contact-form-group {
        margin-bottom: 1.8rem;
    }

    .contact-form label {
        display: block;
        margin-bottom: 0.6rem;
        font-weight: 600;
        color: var(--contact-dark);
    }

    .contact-form input,
    .contact-form textarea,
    .contact-form select {
        width: 100%;
        padding: 0.9rem 1.2rem;
        border: 1px solid var(--contact-gray-200);
        border-radius: 8px;
        font-size: 1rem;
        font-family: inherit;
        transition: border-color 0.3s, box-shadow 0.3s;
    }

    .contact-form input:focus,
    .contact-form textarea:focus,
    .contact-form select:focus {
        outline: none;
        border-color: var(--contact-primary);
        box-shadow: 0 0 0 3px rgba(30,58,95,0.1);
    }

    .contact-form textarea {
        resize: vertical;
        min-height: 140px;
    }

    .contact-submit-btn {
        background: var(--contact-primary);
        color: white;
        border: none;
        padding: 1rem 2.5rem;
        border-radius: 10px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: block;
        margin: 1.5rem auto 0;
    }

    .contact-submit-btn:hover {
        background: var(--contact-primary-light);
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(30,58,95,0.2);
    }

    .contact-info-box {
        text-align: center;
        margin-top: 3rem;
        padding: 2rem;
        background: var(--contact-gray-100);
        border-radius: var(--contact-radius);
    }

    .contact-info-box i {
        font-size: 2.5rem;
        color: var(--contact-accent);
        margin-bottom: 1rem;
    }

    .contact-info-box h3 {
        margin-bottom: 0.5rem;
        color: var(--contact-primary);
    }

    .contact-info-box p {
        color: var(--contact-gray-500);
        line-height: 1.8;
    }

    @media (max-width: 768px) {
        .contact-title { font-size: 2.2rem; }
        .contact-form { padding: 1.8rem; }
    }
</style>

<div class="contact-page">
    <div class="contact-container">
        <h1 class="contact-title">Contactez-nous</h1>

        <?php if ($message): ?>
            <div class="contact-alert success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="contact-alert error">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="contact-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="contact-form-group">
                <label for="nom">Nom complet *</label>
                <input type="text" id="nom" name="nom" 
                       value="<?= htmlspecialchars($user ? trim($user['prenom'] . ' ' . $user['nom']) : ($_POST['nom'] ?? '')) ?>" 
                       required>
            </div>

            <div class="contact-form-group">
                <label for="email">Adresse e-mail *</label>
                <input type="email" id="email" name="email" 
                       value="<?= htmlspecialchars($user['email'] ?? ($_POST['email'] ?? '')) ?>" 
                       required>
            </div>

            <div class="contact-form-group">
                <label for="telephone">Téléphone</label>
                <input type="tel" id="telephone" name="telephone" 
                       value="<?= htmlspecialchars($user['telephone'] ?? ($_POST['telephone'] ?? '')) ?>">
            </div>

            <div class="contact-form-group">
                <label for="type">Type de demande</label>
                <select id="type" name="type">
                    <?php
                    $types = [
                        'question'    => 'Question générale',
                        'probleme'    => 'Problème technique / compte',
                        'suggestion'  => 'Suggestion / idée',
                        'partenariat' => 'Partenariat / proposition',
                        'autre'       => 'Autre'
                    ];
                    $selected = $_POST['type'] ?? 'question';
                    foreach ($types as $val => $label) {
                        $sel = ($val === $selected) ? 'selected' : '';
                        echo "<option value=\"$val\" $sel>" . htmlspecialchars($label) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="contact-form-group">
                <label for="sujet">Sujet *</label>
                <input type="text" id="sujet" name="sujet" 
                       value="<?= htmlspecialchars($_POST['sujet'] ?? '') ?>" required>
            </div>

            <div class="contact-form-group">
                <label for="message">Votre message *</label>
                <textarea id="message" name="message" rows="7" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="contact-submit-btn">Envoyer mon message</button>
        </form>

        <div class="contact-info-box">
            <i class="fas fa-headset"></i>
            <h3>Besoin d'une réponse rapide ?</h3>
            <p>
                support@bibliotech.ma<br>
                WhatsApp : +212 6 234 567 89<br>
                (Lundi–Samedi, 9h–19h)
            </p>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>