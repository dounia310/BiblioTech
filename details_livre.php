<?php
// details_livre.php - VERSION CORRIGÉE

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// DEBUG TEMPORAIRE - RETIRER APRÈS TEST
// ============================================
echo "<!--\n";
echo "=== DEBUG INFORMATION ===\n";
echo "Session ID: " . session_id() . "\n";
echo "User ID in session: " . ($_SESSION['user_id'] ?? 'NULL') . "\n";
echo "All session data:\n";
print_r($_SESSION);
echo "-->\n";

// Test manuel de connexion (facultatif - pour test seulement)
if (isset($_GET['test_login']) && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 999; // ID de test
    $_SESSION['user_name'] = 'Utilisateur Test';
    $_SESSION['user_email'] = 'test@example.com';
    echo "<!-- DEBUG: Connexion test activée -->\n";
}
// ============================================

// VÉRIFICATION D'AUTHENTIFICATION
if (!isset($_SESSION['user_id'])) {
    // Sauvegarder la page demandée
    $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? 'details_livre.php';
    
    // Message d'erreur
    $_SESSION['error_message'] = "Vous devez être connecté pour consulter les détails des livres.";
    
    // DEBUG: Afficher l'info avant redirection
    echo "<!-- DEBUG: Redirection vers login.php -->\n";
    echo "<!-- DEBUG: Login redirect URL: " . ($_SESSION['login_redirect'] ?? 'N/A') . " -->\n";
    
    // Rediriger
    header("Location: login.php");
    exit;
}

// Si on arrive ici, l'utilisateur est connecté
$user_authenticated = true;
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Membre';

echo "<!-- DEBUG: Utilisateur connecté - ID: $user_id, Nom: $user_name -->\n";

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


// Vérifier l'authentification utilisateur
$user_authenticated = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;  // AJOUTE CETTE LIGNE
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

// === Emprunt actuel ===
$emprunt_actuel = null;
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
    $emprunt_actuel = $stmt_em->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur emprunt actuel : " . $e->getMessage());
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

// === Traitement POST ===
$msg = $msg_type = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'reserver' && !$reservation) {
            $dispo = count(array_filter($exemplaires, fn($ex) => $ex['statut'] === 'disponible')) > 0;
            if ($dispo) {
                $stmt_ins = $pdo->prepare("
                    INSERT INTO reservations 
                    (membre_id, livre_id, date_reservation, statut)
                    VALUES (?, ?, NOW(), 'en_attente')
                ");
                $stmt_ins->execute([$user_id, $livre_id]);
                $msg = "Réservation effectuée ! Vous serez notifié quand un exemplaire sera disponible.";
                $msg_type = 'success';
            } else {
                $msg = "Aucun exemplaire disponible pour réservation.";
                $msg_type = 'danger';
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

                    $msg = "Emprunt enregistré ! Retour prévu dans 14 jours.";
                    $msg_type = 'success';
                } else {
                    $msg = "Cet exemplaire n’est plus disponible.";
                    $msg_type = 'danger';
                }
            }
        }

        elseif ($action === 'prolonger' && $emprunt_actuel) {
            $stmt_up = $pdo->prepare("
                UPDATE emprunts 
                SET date_retour_prevue = DATE_ADD(date_retour_prevue, INTERVAL 7 DAY)
                WHERE id = ? AND membre_id = ?
            ");
            $stmt_up->execute([$emprunt_actuel['id'], $user_id]);
            $msg = "Emprunt prolongé de 7 jours.";
            $msg_type = 'success';
        }

        elseif ($action === 'annuler_reservation') {
            $reservation_id = (int)($_POST['reservation_id'] ?? 0);

            if ($reservation_id <= 0) {
                $msg = "ID de réservation invalide.";
                $msg_type = 'danger';
            } else {
                $pdo->beginTransaction();

                // Vérifier existence + appartenance + statut annulable
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
                    $msg = "Impossible d’annuler : statut actuel = " . htmlspecialchars($res['statut']);
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
                        $msg = "Échec de l’annulation (peut-être déjà traitée ou modifiée).";
                        $msg_type = 'warning';
                    }
                }

                $pdo->commit();
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
                SELECT l.*, e.nom AS editeur,
                       COALESCE(AVG(a.note), 0) AS note_moyenne, 
                       COUNT(a.id) AS evaluations_count
                FROM livres l
                LEFT JOIN editeurs e ON l.editeur_id = e.id
                LEFT JOIN evaluations a ON a.livre_id = l.id
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

        .tabs {
            margin: 2rem 0 1rem;
            border-bottom: 2px solid var(--gray);
            display: flex;
            gap: 2rem;
        }

        .tab {
            padding: 0.8rem 0;
            font-weight: 600;
            color: #555;
            cursor: pointer;
            position: relative;
        }

        .tab.active {
            color: var(--primary);
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary);
        }

        .btn {
            padding: 0.8rem 1.6rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary    { background: var(--primary); color: white; border: none; }
        .btn-success    { background: var(--success); color: white; border: none; }
        .btn-warning    { background: var(--warning); color: #856404; border: 2px solid #ffeeba; }
        .btn-danger     { background: var(--danger);  color: white; border: none; }
        .btn-outline    { background: transparent; border: 2px solid var(--primary); color: var(--primary); }

        .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger  { background: #f8d7da; color: #721c24; }
        .alert-warning { background: #fff3cd; color: #856404; }

        @media (max-width: 992px) {
            .hero { grid-template-columns: 1fr; text-align: center; }
            .cover { max-width: 400px; margin: 0 auto; }
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

            <div style="margin-top:2rem; display:flex; gap:1rem; flex-wrap:wrap; justify-content:center;">
                <?php if (count(array_filter($exemplaires, fn($ex) => $ex['statut'] === 'disponible')) > 0 && !$reservation && !$emprunt_actuel): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="reserver">
                        <input type="hidden" name="livre_id" value="<?= $livre_id ?>">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-bookmark"></i> Réserver maintenant
                        </button>
                    </form>

                    <form method="post">
                        <input type="hidden" name="action" value="emprunter">
                        <input type="hidden" name="livre_id" value="<?= $livre_id ?>">
                        <select name="exemplaire_id" required style="padding:0.8rem; border-radius:10px; min-width:160px;">
                            <?php foreach ($exemplaires as $ex): 
                                if ($ex['statut'] === 'disponible'): ?>
                                    <option value="<?= $ex['id'] ?>">
                                        <?= htmlspecialchars($ex['code_inventaire']) ?> – <?= htmlspecialchars($ex['localisation'] ?? '—') ?>
                                    </option>
                                <?php endif; 
                            endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-hand-holding"></i> Emprunter maintenant
                        </button>
                    </form>

                    <!-- Bouton Achat -->
<?php if ($livre['prix'] > 0): ?>
    <form method="post" action="ajouter_au_panier.php">
        <input type="hidden" name="livre_id" value="<?= $livre_id ?>">
        <input type="hidden" name="type" value="achat">
        <input type="hidden" name="quantite" value="1">
        <button type="submit" class="btn btn-primary btn-lg" style="background: #e67e22;">
            <i class="fas fa-shopping-cart"></i> Ajouter au panier (<?= number_format($livre['prix'], 2) ?> DH)
        </button>
    </form>
<?php endif; ?>

                <?php elseif ($emprunt_actuel): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="prolonger">
                        <button type="submit" class="btn btn-outline btn-lg">
                            <i class="fas fa-clock"></i> Prolonger de 7 jours
                        </button>
                    </form>
                    <div class="btn btn-success btn-lg disabled">
                        <i class="fas fa-check"></i> Déjà emprunté par vous
                    </div>

                <?php elseif ($reservation): ?>
                    <div class="btn btn-warning btn-lg" style="display:flex; align-items:center; gap:1rem; padding:1rem 2rem;">
                        <i class="fas fa-hourglass-half fa-lg"></i>
                        <div>
                            <strong>Réservation en attente</strong><br>
                            <small>Position dans la file : <b><?= $reservation['position'] ?? '?' ?></b></small>
                        </div>
                    </div>

                    <form method="post" onsubmit="return confirm('Confirmez-vous l’annulation de votre réservation ?');">
                        <input type="hidden" name="action" value="annuler_reservation">
                        <input type="hidden" name="reservation_id" value="<?= $reservation['id'] ?>">
                        <button type="submit" class="btn btn-outline btn-lg" style="color:var(--danger); border-color:var(--danger);">
                            <i class="fas fa-times"></i> Annuler ma réservation
                        </button>
                    </form>

                <?php else: ?>
                    <div class="btn btn-danger btn-lg disabled">
                        <i class="fas fa-times"></i> Indisponible pour le moment
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="cover">
            <?php if ($livre['couverture_url']): ?>
                <img src="<?= htmlspecialchars($livre['couverture_url']) ?>" alt="<?= htmlspecialchars($livre['titre']) ?>">
            <?php else: ?>
                <div style="height:420px; display:flex; align-items:center; justify-content:center; background:#e1f0f2; color:var(--primary); font-size:6rem; opacity:0.5;">
                    <i class="fas fa-book"></i>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <div class="tab active" data-tab="infos">Informations</div>
        <div class="tab" data-tab="exemplaires">Exemplaires (<?= count($exemplaires) ?>)</div>
        <div class="tab" data-tab="avis">Avis & Note</div>
    </div>

    <div class="tab-content">
        <!-- Onglet Infos -->
        <div id="infos" class="tab-pane">
            <div class="section">
                <h3>Caractéristiques</h3>
                <div class="info-grid">
                    <div class="info-card"><strong>ISBN</strong> <?= htmlspecialchars($livre['isbn'] ?? '—') ?></div>
                    <div class="info-card"><strong>Éditeur</strong> <?= htmlspecialchars($livre['editeur'] ?? '—') ?></div>
                    <div class="info-card"><strong>Genre principal</strong> <?= htmlspecialchars($livre['genre_principal'] ?? '—') ?></div>
                    <div class="info-card"><strong>Prix</strong> <?= number_format($livre['prix'] ?? 0, 2) ?> DH</div>
                    <div class="info-card"><strong>Statut</strong> <?= htmlspecialchars($livre['statut_bibliographique'] ?? '—') ?></div>
                </div>
            </div>
        </div>

        <!-- Onglet Exemplaires -->
        <div id="exemplaires" class="tab-pane" style="display:none;">
            <div class="section">
                <h3>Exemplaires disponibles</h3>
                <?php if (!empty($exemplaires)): ?>
                    <table style="width:100%; border-collapse:collapse; background:white; border-radius:var(--radius); overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.1);">
                        <thead style="background:var(--primary); color:white;">
                            <tr>
                                <th style="padding:1rem;">Code inventaire</th>
                                <th>État</th>
                                <th>Localisation</th>
                                <th>Statut</th>
                                <th>Date acquisition</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exemplaires as $ex): ?>
                                <tr style="border-bottom:1px solid #eee;">
                                    <td style="padding:1rem;"><?= htmlspecialchars($ex['code_inventaire'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($ex['etat'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($ex['localisation'] ?? '—') ?></td>
                                    <td style="color: <?= $ex['statut']==='disponible' ? '#155724' : '#721c24' ?>; font-weight:600;">
                                        <?= htmlspecialchars($ex['statut']) ?>
                                    </td>
                                    <td><?= $ex['date_acquisition'] ? date('d/m/Y', strtotime($ex['date_acquisition'])) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align:center; color:#777; padding:3rem;">Aucun exemplaire enregistré pour ce livre.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Onglet Avis -->
        <div id="avis" class="tab-pane" style="display:none;">
            <div class="section">
                <h3>Donner votre avis</h3>
                <form method="post" class="avis-form">
                    <input type="hidden" name="action" value="noter">
                    <input type="hidden" name="livre_id" value="<?= $livre_id ?>">

                    <div style="margin-bottom:1.5rem;">
                        <label>Note (1-5) :</label><br>
                        <div class="stars">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="fa-star far" data-note="<?= $i ?>" style="cursor:pointer; font-size:2rem; margin:0 4px;"></i>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="note" id="note" value="0" required>
                    </div>

                    <div style="margin-bottom:1.5rem;">
                        <label>Commentaire (facultatif) :</label><br>
                        <textarea name="commentaire" rows="4" style="width:100%; padding:0.8rem; border-radius:10px; border:1px solid #ddd;"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Envoyer mon avis</button>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
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