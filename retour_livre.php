<?php
// retour_livre.php - Page de confirmation et traitement du retour d'un livre
session_start();
require_once 'connexion.php';

// Sécurité
if (!isset($_SESSION['user_id']) || !isset($_SESSION['membre_id'])) {
    header('Location: login.php?redirect=mes_emprunts.php');
    exit;
}

$membre_id  = (int) ($_SESSION['membre_id'] ?? $_SESSION['user_id']);
$emprunt_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// ID invalide → retour immédiat
if ($emprunt_id <= 0) {
    $_SESSION['error_message'] = "ID d'emprunt invalide.";
    header('Location: mes_emprunts.php');
    exit;
}

try {
    // Récupérer l'emprunt + vérifications strictes
    $stmt = $pdo->prepare("
        SELECT 
            e.id,
            e.membre_id,
            e.statut,
            e.date_emprunt,
            e.date_retour_prevue,
            e.date_retour_effective,
            e.amende_cumulee,
            e.exemplaire_id,
            e.prolongations,
            l.titre,
            l.id AS livre_id,
            ex.code_barre,
            ex.statut AS statut_exemplaire
        FROM emprunts e
        JOIN exemplaires ex ON e.exemplaire_id = ex.id
        JOIN livres l ON ex.livre_id = l.id
        WHERE e.id = ?
          AND e.membre_id = ?
          AND e.date_retour_effective IS NULL
          AND (e.statut = 'en_cours' OR e.statut IS NULL OR e.statut = '')
    ");
    $stmt->execute([$emprunt_id, $membre_id]);
    $emprunt = $stmt->fetch();

    if (!$emprunt) {
        // Debug supplémentaire
        $check = $pdo->prepare("SELECT id, membre_id, statut, date_retour_effective FROM emprunts WHERE id = ?");
        $check->execute([$emprunt_id]);
        $info = $check->fetch();

        if (!$info) {
            $_SESSION['error_message'] = "Emprunt #$emprunt_id introuvable.";
        } elseif ($info['membre_id'] != $membre_id) {
            $_SESSION['error_message'] = "Cet emprunt ne vous appartient pas.";
        } elseif (!empty($info['date_retour_effective'])) {
            $_SESSION['error_message'] = "Cet emprunt a déjà été retourné.";
        } else {
            $_SESSION['error_message'] = "Impossible de retourner cet emprunt (statut invalide).";
        }

        header('Location: mes_emprunts.php');
        exit;
    }

    // Calcul retard + amende
    $date_prevue = new DateTime($emprunt['date_retour_prevue']);
    $aujourdhui  = new DateTime();
    $jours_retard = 0;
    $amende       = 0;

    if ($aujourdhui > $date_prevue) {
        $interval     = $aujourdhui->diff($date_prevue);
        $jours_retard = $interval->days;
        $amende       = min($jours_retard * 0.5, 10); // 0.5 DH/jour, max 10 DH
    }

    // Traitement POST → retour effectif
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();

        try {
            // 1. Mettre à jour l'emprunt
            $stmt = $pdo->prepare("
                UPDATE emprunts 
                SET 
                    date_retour_effective = NOW(),
                    statut = 'retourne',
                    amende_cumulee = ?,
                    notes = CONCAT(
                        COALESCE(notes, ''),
                        ' | Retour confirmé le ', 
                        DATE_FORMAT(NOW(), '%d/%m/%Y à %H:%i')
                    )
                WHERE id = ?
                  AND membre_id = ?
                  AND date_retour_effective IS NULL
            ");
            $stmt->execute([$amende, $emprunt_id, $membre_id]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("Échec mise à jour emprunt – possiblement déjà retourné.");
            }

            // 2. Libérer l'exemplaire
                    $stmt = $pdo->prepare("
            UPDATE exemplaires 
            SET statut = 'disponible'
            WHERE id = ?
        ");
        $stmt->execute([$emprunt['exemplaire_id']]);

        
                    // 3. Créer une amende si retard
            if ($amende > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO amendes 
                    (membre_id, emprunt_id, montant, description, statut, date_creation)
                    VALUES (?, ?, ?, ?, 'impaye', NOW())
                ");
                $stmt->execute([
                    $membre_id,
                    $emprunt_id,
                    $amende,
                    "Retard de $jours_retard jour(s) – Livre : " . $emprunt['titre']
                ]);
            }

            $pdo->commit();

            // Succès → redirection vers confirmation
            $_SESSION['retour_success'] = "Le retour a été enregistré avec succès.";
            $_SESSION['retour_livre']   = $emprunt['titre'];
            $_SESSION['retour_amende']  = $amende;

            header('Location: retour_confirmation.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Erreur lors du retour : " . $e->getMessage();
            error_log("Retour livre échoué : " . $e->getMessage());
        }
    }

} catch (PDOException $e) {
    $_SESSION['error_message'] = "Erreur base de données : " . $e->getMessage();
    error_log("Erreur PDO dans retour_livre.php : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retourner un livre - Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #1e3a5f;
            --primary-light: #2d5a87;
            --accent: #e8b86d;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray-100: #f1f5f9;
            --light: #ffffff;
            --radius: 12px;
            --shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #1f2937;
        }

        .card {
            background: var(--light);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 680px;
            overflow: hidden;
        }

        .card-header {
            background: var(--primary);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .card-header h1 {
            margin: 0;
            font-size: 1.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .card-body {
            padding: 2.5rem;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .book-info {
            background: var(--gray-100);
            border-radius: 10px;
            padding: 1.8rem;
            margin-bottom: 2rem;
        }

        .book-title {
            color: var(--primary);
            font-size: 1.6rem;
            margin: 0 0 1rem 0;
        }

        .info-row {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .info-item {
            flex: 1;
            min-width: 180px;
        }

        .label {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
            display: block;
        }

        .value {
            font-weight: 600;
            color: #1e293b;
        }

        .penalty {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            margin: 1.5rem 0;
        }

        .penalty-amount {
            font-size: 2.2rem;
            color: var(--danger);
            font-weight: 700;
            margin: 0.8rem 0;
        }

        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.9rem 2rem;
            border-radius: 8px;
            font-size: 1.05rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            transition: all 0.25s;
            min-width: 220px;
            justify-content: center;
        }

        .btn-confirm {
            background: var(--success);
            color: white;
            box-shadow: 0 4px 12px rgba(16,185,129,0.3);
        }

        .btn-confirm:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: #e2e8f0;
            color: var(--primary);
        }

        .btn-cancel:hover {
            background: #cbd5e1;
        }

        @media (max-width: 600px) {
            .card-body { padding: 1.8rem; }
            .actions { flex-direction: column; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>

<div class="card">
    <div class="card-header">
        <h1><i class="fas fa-undo-alt"></i> Retourner un livre</h1>
    </div>

    <div class="card-body">

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($_SESSION['error_message']) ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <div class="book-info">
            <h2 class="book-title"><?= htmlspecialchars($emprunt['titre']) ?></h2>

            <div class="info-row">
                <div class="info-item">
                    <span class="label">Emprunté le</span>
                    <span class="value"><?= date('d/m/Y', strtotime($emprunt['date_emprunt'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Retour prévu le</span>
                    <span class="value"><?= date('d/m/Y', strtotime($emprunt['date_retour_prevue'])) ?></span>
                </div>
            </div>

            <?php if ($jours_retard > 0): ?>
                <div class="penalty">
                    <strong style="font-size:1.3rem; color:#c2410c;">Retard détecté</strong><br>
                    <div class="penalty-amount"><?= number_format($amende, 2) ?> DH</div>
                    <div style="font-size:1.1rem; color:#92400e;">
                        Retard de <strong><?= $jours_retard ?> jour(s)</strong>
                    </div>
                    <small>(0,50 DH/jour – max. 10 DH)</small>
                </div>
            <?php else: ?>
                <div style="background:#ecfdf5; padding:1.5rem; border-radius:10px; text-align:center; margin:1.5rem 0;">
                    <i class="fas fa-check-circle" style="color:var(--success); font-size:2.2rem; margin-bottom:0.8rem; display:block;"></i>
                    <strong style="color:#065f46; font-size:1.3rem;">Retour dans les délais</strong><br>
                    <span style="color:#065f46;">Aucune amende appliquée</span>
                </div>
            <?php endif; ?>
        </div>

        <div style="background:#e0f2fe; padding:1.8rem; border-radius:10px; text-align:center; margin-bottom:2rem;">
            <h3 style="margin:0 0 1rem 0; color:var(--primary);">Confirmez-vous le retour ?</h3>
            <p style="margin:0; color:#1e40af; font-size:1.1rem;">
                Cette action est définitive et libérera l’exemplaire pour d’autres membres.
            </p>
        </div>

        <form method="POST" class="actions">
            <button type="submit" name="confirm_return" class="btn btn-confirm">
                <i class="fas fa-check"></i> Oui, confirmer le retour
            </button>
            <a href="mes_emprunts.php" class="btn btn-cancel">
                <i class="fas fa-times"></i> Annuler
            </a>
        </form>
    </div>
</div>

</body>
</html>