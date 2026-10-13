<?php
// details_livre.php - VERSION AVEC RESERVATION INTELLIGENTE

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// VÉRIFICATION D'AUTHENTIFICATION
if (!isset($_SESSION['user_id'])) {
    $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? 'details_livre.php';
    $_SESSION['error_message'] = "Vous devez être connecté pour consulter les détails des livres.";
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Membre';

// Vérifier l'ID du livre
$livre_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$livre_id) {
    $_SESSION['error_message'] = "Livre non spécifié.";
    header("Location: catalogue.php");
    exit;
}

require_once 'connexion.php';

// === Récupérer le livre + note moyenne ===
$livre = null;
try {
    $stmt = $pdo->prepare("
        SELECT 
            l.*, 
            a.nom_complet AS auteur,  
            e.nom AS editeur,
            COALESCE(AVG(ev.note), 0) AS note_moyenne, 
            COUNT(ev.id) AS evaluations_count
        FROM livres l
        LEFT JOIN auteurs a ON l.auteur_id = a.id  
        LEFT JOIN editeurs e ON l.editeur_id = e.id
        LEFT JOIN evaluations ev ON ev.livre_id = l.id
        WHERE l.id = ?
        GROUP BY l.id
    ");
    $stmt->execute([$livre_id]);
    $livre = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur chargement livre : " . $e->getMessage());
    die("Erreur lors du chargement du livre.");
}

if (!$livre) {
    header("Location: catalogue.php");
    exit;
}

// === Exemplaires ===
$exemplaires = [];
try {
    $stmt_ex = $pdo->prepare("
        SELECT * FROM exemplaires 
        WHERE livre_id = ? 
        ORDER BY statut, code_inventaire
    ");
    $stmt_ex->execute([$livre_id]);
    $exemplaires = $stmt_ex->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur exemplaires : " . $e->getMessage());
}

// === Emprunt actuel PAR L'UTILISATEUR CONNECTÉ ===
$emprunt_actuel_user = null;
try {
    $stmt_em = $pdo->prepare("
        SELECT e.* 
        FROM emprunts e
        JOIN exemplaires ex ON e.exemplaire_id = ex.id
        WHERE e.membre_id = ? 
          AND ex.livre_id = ?
          AND e.date_retour_effective IS NULL
        LIMIT 1
    ");
    $stmt_em->execute([$user_id, $livre_id]);
    $emprunt_actuel_user = $stmt_em->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur emprunt actuel : " . $e->getMessage());
}

// === VÉRIFIER SI LE LIVRE EST EMPRUNTÉ PAR QUELQU'UN D'AUTRE ===
$livre_emprunte_par_autre = false;
$date_retour_proche = null;
try {
    // Compter les emprunts en cours par d'autres membres
    $stmt_autre = $pdo->prepare("
        SELECT COUNT(*) as count, MIN(e.date_retour_prevue) as prochain_retour
        FROM emprunts e
        JOIN exemplaires ex ON e.exemplaire_id = ex.id
        WHERE ex.livre_id = ?
          AND e.membre_id != ?
          AND e.date_retour_effective IS NULL
    ");
    $stmt_autre->execute([$livre_id, $user_id]);
    $result = $stmt_autre->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        $livre_emprunte_par_autre = true;
        $date_retour_proche = $result['prochain_retour'];
    }
} catch (PDOException $e) {
    error_log("Erreur vérification emprunt autre : " . $e->getMessage());
}

// === Réservation en attente ===
$reservation = null;
try {
    $stmt_res = $pdo->prepare("
        SELECT * FROM reservations 
        WHERE membre_id = ? 
          AND livre_id = ? 
          AND statut = 'en_attente'
        LIMIT 1
    ");
    $stmt_res->execute([$user_id, $livre_id]);
    $reservation = $stmt_res->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur réservation : " . $e->getMessage());
}

// === Compter les exemplaires disponibles ===
$exemplaires_disponibles = array_filter($exemplaires, fn($ex) => $ex['statut'] === 'disponible');
$nombre_exemplaires_disponibles = count($exemplaires_disponibles);

// === Traitement POST ===
$msg = $msg_type = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'reserver' && !$reservation) {
            // LOGIQUE DE RÉSERVATION INTELLIGENTE :
            // 1. On peut réserver seulement si le livre est emprunté par quelqu'un d'autre
            // 2. On peut réserver même s'il y a des exemplaires disponibles (pour être sûr)
            // 3. On ne peut pas réserver si on a déjà emprunté le livre
            
            if ($emprunt_actuel_user) {
                $msg = "Vous avez déjà emprunté ce livre. Impossible de réserver.";
                $msg_type = 'warning';
            } elseif ($nombre_exemplaires_disponibles > 0 && !$livre_emprunte_par_autre) {
                // Il y a des exemplaires disponibles et personne d'autre ne l'a emprunté
                $msg = "Des exemplaires sont disponibles. Vous pouvez l'emprunter directement.";
                $msg_type = 'info';
            } else {
                // Livre emprunté par quelqu'un d'autre OU on veut réserver malgré la disponibilité
                // Calculer la position dans la file d'attente
                $stmt_position = $pdo->prepare("
                    SELECT COUNT(*) as position 
                    FROM reservations 
                    WHERE livre_id = ? 
                    AND statut = 'en_attente'
                ");
                $stmt_position->execute([$livre_id]);
                $position_result = $stmt_position->fetch(PDO::FETCH_ASSOC);
                $position = ($position_result['position'] ?? 0) + 1;
                
                $stmt_ins = $pdo->prepare("
                    INSERT INTO reservations 
                    (membre_id, livre_id, date_reservation, statut, position)
                    VALUES (?, ?, NOW(), 'en_attente', ?)
                ");
                $stmt_ins->execute([$user_id, $livre_id, $position]);
                $msg = "Réservation effectuée ! Vous êtes en position $position dans la file d'attente.";
                $msg_type = 'success';
            }
        }

        elseif ($action === 'emprunter') {
            $ex_id = (int)($_POST['exemplaire_id'] ?? 0);
            if ($ex_id <= 0) {
                $msg = "Aucun exemplaire sélectionné.";
                $msg_type = 'danger';
            } else {
                $check = $pdo->prepare("
                    SELECT id FROM exemplaires 
                    WHERE id = ? AND livre_id = ? AND statut = 'disponible'
                ");
                $check->execute([$ex_id, $livre_id]);
                if ($check->fetch()) {
                    $stmt_ins = $pdo->prepare("
                        INSERT INTO emprunts 
                        (membre_id, exemplaire_id, date_emprunt, date_retour_prevue)
                        VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY))
                    ");
                    $stmt_ins->execute([$user_id, $ex_id]);

                    $pdo->prepare("UPDATE exemplaires SET statut = 'emprunte' WHERE id = ?")
                        ->execute([$ex_id]);

                    // Annuler la réservation si l'utilisateur en avait une
                    if ($reservation) {
                        $pdo->prepare("DELETE FROM reservations WHERE id = ?")
                            ->execute([$reservation['id']]);
                    }

                    $msg = "Emprunt enregistré ! Retour prévu dans 14 jours.";
                    $msg_type = 'success';
                } else {
                    $msg = "Cet exemplaire n'est plus disponible.";
                    $msg_type = 'danger';
                }
            }
        }

        elseif ($action === 'prolonger' && $emprunt_actuel_user) {
            $stmt_up = $pdo->prepare("
                UPDATE emprunts 
                SET date_retour_prevue = DATE_ADD(date_retour_prevue, INTERVAL 7 DAY)
                WHERE id = ? AND membre_id = ?
            ");
            $stmt_up->execute([$emprunt_actuel_user['id'], $user_id]);
            $msg = "Emprunt prolongé de 7 jours.";
            $msg_type = 'success';
        }

        elseif ($action === 'annuler_reservation') {
            $reservation_id = (int)($_POST['reservation_id'] ?? 0);

            if ($reservation_id <= 0) {
                $msg = "ID de réservation invalide.";
                $msg_type = 'danger';
            } else {
                // Vérifier existence + appartenance
                $check = $pdo->prepare("
                    SELECT id, statut 
                    FROM reservations 
                    WHERE id = ? AND membre_id = ?
                ");
                $check->execute([$reservation_id, $user_id]);
                $res = $check->fetch(PDO::FETCH_ASSOC);

                if (!$res) {
                    $msg = "Cette réservation n'existe pas ou ne vous appartient pas.";
                    $msg_type = 'danger';
                } elseif ($res['statut'] !== 'en_attente') {
                    $msg = "Impossible d'annuler : statut actuel = " . htmlspecialchars($res['statut']);
                    $msg_type = 'warning';
                } else {
                    $stmt_del = $pdo->prepare("
                        DELETE FROM reservations 
                        WHERE id = ? AND membre_id = ? AND statut = 'en_attente'
                    ");
                    $stmt_del->execute([$reservation_id, $user_id]);

                    if ($stmt_del->rowCount() > 0) {
                        $msg = "Votre réservation a été annulée avec succès.";
                        $msg_type = 'success';
                    } else {
                        $msg = "Échec de l'annulation (peut-être déjà traitée ou modifiée).";
                        $msg_type = 'warning';
                    }
                }
            }
        }

        elseif ($action === 'noter') {
            $note = (int)($_POST['note'] ?? 0);
            $commentaire = trim($_POST['commentaire'] ?? '');

            if ($note >= 1 && $note <= 5) {
                $stmt_note = $pdo->prepare("
                    INSERT INTO evaluations 
                    (livre_id, membre_id, note, commentaire, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                        note = VALUES(note), 
                        commentaire = VALUES(commentaire), 
                        updated_at = NOW()
                ");
                $stmt_note->execute([$livre_id, $user_id, $note, $commentaire]);
                $msg = "Votre avis a été enregistré. Merci !";
                $msg_type = 'success';
            } else {
                $msg = "La note doit être entre 1 et 5.";
                $msg_type = 'danger';
            }
        }
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $msg = "Erreur technique : " . $e->getMessage();
        $msg_type = 'danger';
        error_log("Erreur POST details_livre : " . $e->getMessage());
    }

    // Recharge le livre après action réussie
    if ($msg_type === 'success') {
        try {
            $stmt_reload = $pdo->prepare("
                SELECT l.*, a.nom_complet AS auteur, e.nom AS editeur,
                       COALESCE(AVG(ev.note), 0) AS note_moyenne, 
                       COUNT(ev.id) AS evaluations_count
                FROM livres l
                LEFT JOIN auteurs a ON l.auteur_id = a.id  
                LEFT JOIN editeurs e ON l.editeur_id = e.id
                LEFT JOIN evaluations ev ON ev.livre_id = l.id
                WHERE l.id = ?
                GROUP BY l.id
            ");
            $stmt_reload->execute([$livre_id]);
            $livre = $stmt_reload->fetch(PDO::FETCH_ASSOC) ?: $livre;
        } catch (PDOException $e) {
            error_log("Erreur rechargement livre : " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($livre['titre'] ?? 'Livre') ?> • Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #2b848e;
            --success: #7ab781;
            --warning: #ffc107;
            --danger:  #dc3545;
            --dark:   #254c5b;
            --light:  #f8f9fa;
            --gray:   #e9ecef;
            --radius: 12px;
        }

        body { background: var(--light); font-family: system-ui, sans-serif; color: var(--dark); }

        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1.5rem; }

        .hero {
            background: linear-gradient(135deg, var(--dark) 0%, var(--primary) 100%);
            color: white;
            border-radius: var(--radius);
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(37,76,91,0.2);
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 2rem;
            align-items: start;
        }

        .hero-left h1 { font-size: 2.6rem; margin: 0 0 0.8rem; }
        .hero-left .author { font-size: 1.3rem; opacity: 0.9; font-style: italic; }

        .meta { display: flex; gap: 1.5rem; flex-wrap: wrap; margin: 1rem 0; }
        .meta-item { display: flex; align-items: center; gap: 0.5rem; }

        .cover img {
            width: 100%;
            border-radius: var(--radius);
            box-shadow: 0 12px 40px rgba(0,0,0,0.3);
        }

        .availability-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 1rem 1.5rem;
            margin: 1.5rem 0;
            border-left: 4px solid #ffc107;
        }

        .btn {
            padding: 0.8rem 1.6rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary    { background: var(--primary); color: white; }
        .btn-success    { background: var(--success); color: white; }
        .btn-warning    { background: var(--warning); color: #856404; }
        .btn-danger     { background: var(--danger);  color: white; }
        .btn-outline    { background: transparent; border: 2px solid var(--primary); color: var(--primary); }
        .btn-secondary  { background: #6c757d; color: white; }

        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger  { background: #f8d7da; color: #721c24; }
        .alert-warning { background: #fff3cd; color: #856404; }
        .alert-info    { background: #d1ecf1; color: #0c5460; }

        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin: 2rem 0;
            align-items: center;
        }

        @media (max-width: 992px) {
            .hero { grid-template-columns: 1fr; text-align: center; }
            .cover { max-width: 400px; margin: 0 auto; }
            .action-buttons { justify-content: center; }
        }
    </style>
</head>
<body>

<div class="container">

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="hero">
        <div class="hero-left">
            <h1><?= htmlspecialchars($livre['titre']) ?></h1>
            <div class="author">par <?= htmlspecialchars($livre['auteur'] ?? '—') ?></div>

            <div class="meta">
                <div class="meta-item"><i class="fas fa-calendar"></i> <?= $livre['annee_publication'] ?? '—' ?></div>
                <div class="meta-item"><i class="fas fa-book"></i> <?= $livre['nombre_pages'] ?? '?' ?> pages</div>
                <div class="meta-item"><i class="fas fa-star"></i> <?= number_format($livre['note_moyenne'], 1) ?> / 5 (<?= $livre['evaluations_count'] ?> avis)</div>
            </div>

            <?php if ($livre['description']): ?>
                <p style="margin: 1.5rem 0; line-height:1.6;">
                    <?= nl2br(htmlspecialchars($livre['description'])) ?>
                </p>
            <?php endif; ?>

            <!-- INFO DISPONIBILITÉ -->
            <div class="availability-info">
                <h4 style="margin: 0 0 0.5rem 0; color: white;"><i class="fas fa-info-circle"></i> Disponibilité</h4>
                <?php if ($emprunt_actuel_user): ?>
                    <p style="margin: 0; color: #c3e6cb;">
                        ✅ <strong>Vous avez emprunté ce livre</strong><br>
                        Retour prévu le : <?= date('d/m/Y', strtotime($emprunt_actuel_user['date_retour_prevue'])) ?>
                    </p>
                <?php elseif ($nombre_exemplaires_disponibles > 0): ?>
                    <p style="margin: 0; color: #c3e6cb;">
                        ✅ <strong>Disponible immédiatement</strong><br>
                        <?= $nombre_exemplaires_disponibles ?> exemplaire(s) disponible(s)
                    </p>
                <?php elseif ($livre_emprunte_par_autre): ?>
                    <p style="margin: 0; color: #ffeaa7;">
                        ⏳ <strong>Emprunté par un autre membre</strong><br>
                        <?php if ($date_retour_proche): ?>
                            Prochain retour prévu le : <?= date('d/m/Y', strtotime($date_retour_proche)) ?>
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p style="margin: 0; color: #f5c6cb;">
                        ❌ <strong>Indisponible</strong><br>
                        Tous les exemplaires sont empruntés ou en maintenance
                    </p>
                <?php endif; ?>
            </div>

            <!-- BOUTONS D'ACTION -->
            <div class="action-buttons">
                <?php if ($emprunt_actuel_user): ?>
                    <!-- Si l'utilisateur a déjà emprunté le livre -->
                    <form method="post">
                        <input type="hidden" name="action" value="prolonger">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-clock"></i> Prolonger de 7 jours
                        </button>
                    </form>
                    <div class="btn btn-success" style="cursor: default;">
                        <i class="fas fa-check"></i> Déjà emprunté
                    </div>

                <?php elseif ($nombre_exemplaires_disponibles > 0): ?>
                    <!-- Si des exemplaires sont disponibles -->
                    <?php if (count($exemplaires_disponibles) > 0): ?>
                        <form method="post" style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="hidden" name="action" value="emprunter">
                            <select name="exemplaire_id" required style="padding:0.8rem; border-radius:10px; min-width:180px;">
                                <?php foreach ($exemplaires_disponibles as $ex): ?>
                                    <option value="<?= $ex['id'] ?>">
                                        <?= htmlspecialchars($ex['code_inventaire']) ?> – <?= htmlspecialchars($ex['localisation'] ?? '—') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-hand-holding"></i> Emprunter
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Option de réservation même si disponible -->
                    <?php if (!$reservation): ?>
                        <form method="post">
                            <input type="hidden" name="action" value="reserver">
                            <button type="submit" class="btn btn-outline" title="Réserver pour être sûr d'avoir le livre">
                                <i class="fas fa-bookmark"></i> Réserver quand même
                            </button>
                        </form>
                    <?php endif; ?>

                <?php elseif ($livre_emprunte_par_autre || $nombre_exemplaires_disponibles == 0): ?>
                    <!-- Si le livre est emprunté par quelqu'un d'autre ou indisponible -->
                    <?php if (!$reservation): ?>
                        <form method="post">
                            <input type="hidden" name="action" value="reserver">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-bookmark"></i> Réserver ce livre
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($livre_emprunte_par_autre): ?>
                        <div class="btn btn-secondary" style="cursor: default;">
                            <i class="fas fa-user-friends"></i> Emprunté par un autre membre
                        </div>
                    <?php else: ?>
                        <div class="btn btn-danger" style="cursor: default;">
                            <i class="fas fa-times"></i> Indisponible
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

                <!-- Gestion de la réservation existante -->
                <?php if ($reservation): ?>
                    <div style="display: flex; gap: 1rem; align-items: center; background: #fff3cd; padding: 1rem; border-radius: 10px; width: 100%;">
                        <div style="flex: 1;">
                            <strong><i class="fas fa-hourglass-half"></i> Réservation en attente</strong><br>
                            <small>Position dans la file : <b><?= $reservation['position'] ?? '?' ?></b></small>
                        </div>
                        <form method="post" onsubmit="return confirm('Confirmez-vous l\'annulation de votre réservation ?');">
                            <input type="hidden" name="action" value="annuler_reservation">
                            <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-times"></i> Annuler
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Bouton Achat -->
                <?php if ($livre['prix'] > 0): ?>
                    <form method="post" action="ajouter_au_panier.php" style="margin-left: auto;">
                        <input type="hidden" name="livre_id" value="<?= $livre_id ?>">
                        <input type="hidden" name="type" value="achat">
                        <input type="hidden" name="quantite" value="1">
                        <button type="submit" class="btn btn-primary" style="background: #e67e22;">
                            <i class="fas fa-shopping-cart"></i> Acheter (<?= number_format($livre['prix'], 2) ?> DH)
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="cover">
            <?php if ($livre['couverture_url']): ?>
                <img src="<?= htmlspecialchars($livre['couverture_url']) ?>" alt="<?= htmlspecialchars($livre['titre']) ?>">
            <?php else: ?>
                <div style="height:420px; display:flex; align-items:center; justify-content:center; background:#e1f0f2; color:var(--primary); font-size:6rem; opacity:0.5; border-radius: var(--radius);">
                    <i class="fas fa-book"></i>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Informations supplémentaires... (garder le reste du code) -->
    <!-- ... -->

</div>

<script>
// Script pour les étoiles et les tabs (garder le même)
document.addEventListener('DOMContentLoaded', () => {
    // Tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
            document.getElementById(tab.dataset.tab).style.display = 'block';
        });
    });

    // Étoiles interactives
    document.querySelectorAll('.stars .fa-star').forEach(star => {
        star.addEventListener('click', () => {
            const note = star.dataset.note;
            document.getElementById('note').value = note;
            document.querySelectorAll('.stars .fa-star').forEach(s => {
                s.classList.remove('fas');
                s.classList.add('far');
                if (s.dataset.note <= note) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                }
            });
        });
    });
});
</script>

<?php require_once 'footer.php'; ?>
</body>
</html>