<?php
require_once 'connexion.php';
session_start();

// Protection admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: login.php');
    exit();
}

$membre_id = (int)$_SESSION['user_id'];
$amende_id = (int)($_GET['id'] ?? 0);

if ($amende_id <= 0) {
    header('Location: mes_amendes.php');
    exit;
}

// Notifications (comme dans tes autres pages)
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

// Récupération de l'amende (sécurisée : appartient bien au membre connecté)
$stmt = $pdo->prepare("
    SELECT * FROM amendes 
    WHERE id = ? AND membre_id = ? 
    LIMIT 1
");
$stmt->execute([$amende_id, $membre_id]);
$amende = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$amende) {
    $error = "Amende introuvable ou vous n'êtes pas autorisé à la consulter.";
} else {
    $montant = (float)$amende['montant'];
    $paye = (float)$amende['montant_paye'];
    $reste = max(0, $montant - $paye);

    $message = '';
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $amount = (float)($_POST['amount'] ?? 0);

        if ($amount <= 0 || $amount > $reste) {
            $error = "Montant invalide (maximum " . number_format($reste, 2) . " DH).";
        } else {
            try {
                $nouveau_paye = $paye + $amount;
                $nouveau_statut = ($nouveau_paye >= $montant) ? 'paye' : 'partiel';

                // Mise à jour de l'amende
                $upd = $pdo->prepare("
                    UPDATE amendes
                    SET montant_paye = ?,
                        statut = ?,
                        date_paiement = CASE WHEN ? = 'paye' THEN CURDATE() ELSE date_paiement END,
                        methode_paiement = 'en_ligne',
                        updated_at = NOW()
                    WHERE id = ? AND membre_id = ?
                ");
                $upd->execute([$nouveau_paye, $nouveau_statut, $nouveau_statut, $amende_id, $membre_id]);

                // Log d'audit (optionnel mais recommandé)
                $log = $pdo->prepare("
                    INSERT INTO logs_audit (table_name, record_id, action, user_id, user_type)
                    VALUES ('amendes', ?, 'PAIEMENT', ?, 'membre')
                ");
                $log->execute([$amende_id, $membre_id]);

                $message = "Paiement de " . number_format($amount, 2) . " DH enregistré avec succès !";
                // Option : rediriger après succès
                // header('Location: mes_amendes.php?success=1'); exit;
            } catch (PDOException $e) {
                $error = "Erreur lors de l'enregistrement du paiement.";
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
    <title>Payer une amende - BiblioTech</title>
    
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
            padding: 2rem;
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

        .info-box {
            background: var(--gray-100);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-left: 5px solid var(--primary);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 12px 0;
            font-size: 1.1rem;
        }

        .info-label {
            font-weight: 600;
            color: var(--gray-600);
        }

        .info-value {
            font-weight: 700;
            color: var(--gray-700);
        }

        .reste-danger { color: var(--danger); font-weight: 700; }
        .reste-success { color: var(--success); font-weight: 700; }

        .alert-success {
            background: rgba(16,185,129,0.1);
            color: var(--success);
            padding: 1rem;
            border-radius: var(--radius);
            margin: 1rem 0;
            text-align: center;
        }

        .alert-danger {
            background: rgba(220,38,38,0.1);
            color: var(--danger);
            padding: 1rem;
            border-radius: var(--radius);
            margin: 1rem 0;
            text-align: center;
        }

        .form-group {
            margin: 1.5rem 0;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .form-input {
            width: 100%;
            max-width: 300px;
            padding: 10px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius);
            font-size: 1rem;
        }

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

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-light);
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

        .back-link:hover {
            color: var(--primary-light);
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="admin-header">
        <div class="admin-header-left">
            <a href="dashboard_membre.php" class="dashboard-link"> <!-- ou ton dashboard membre -->
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
                    <?= strtoupper(substr($_SESSION['user_name'] ?? 'M', 0, 1)) ?>
                </div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Membre') ?></strong>
                    <span>Membre</span>
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
                    <i class="fas fa-money-bill-wave"></i>
                    Payer une amende
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (isset($message)): ?>
                <div class="alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (empty($amende)): ?>
                <p class="alert-danger">Amende introuvable ou non autorisée.</p>
            <?php else: ?>

                <div class="info-box">
                    <div class="info-row">
                        <div class="info-label">Montant total</div>
                        <div class="info-value"><?= number_format($montant, 2) ?> DH</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Déjà payé</div>
                        <div class="info-value"><?= number_format($paye, 2) ?> DH</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Reste à payer</div>
                        <div class="info-value <?= $reste_class ?>"><?= number_format($reste, 2) ?> DH</div>
                    </div>
                </div>

                <?php if ($reste <= 0): ?>
                    <p class="alert-success">Cette amende est déjà entièrement réglée.</p>
                <?php else: ?>
                    <form method="POST" class="form-group">
                        <label class="form-label" for="amount">Montant à payer (max <?= number_format($reste, 2) ?> DH)</label>
                        <input type="number" step="0.01" min="0.01" max="<?= htmlspecialchars($reste) ?>" name="amount" id="amount" value="<?= htmlspecialchars($reste) ?>" class="form-input" required>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Confirmer le paiement</button>
                    </form>
                <?php endif; ?>

            <?php endif; ?>

            <a href="mes_amendes.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Retour à mes amendes
            </a>
        </section>

    </div>

</body>
</html>