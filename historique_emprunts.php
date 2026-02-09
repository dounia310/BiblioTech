<?php
session_start();
require_once 'connexion.php';

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupérer les paramètres de filtrage
$statut = $_GET['statut'] ?? 'tous';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';
$tri = $_GET['tri'] ?? 'date_emprunt';
$ordre = $_GET['ordre'] ?? 'DESC';

// Construire la requête - CORRIGÉ (membre_id et date_retour_effective)
$sql = "SELECT e.*, 
               l.titre, l.auteur, l.couverture_url,
               ex.code_barre,
               CASE 
                   WHEN e.statut = 'en_cours' AND e.date_retour_prevue < CURDATE() 
                   THEN DATEDIFF(CURDATE(), e.date_retour_prevue)
                   ELSE 0 
               END as jours_retard
        FROM emprunts e
        JOIN exemplaires ex ON e.exemplaire_id = ex.id
        JOIN livres l ON ex.livre_id = l.id
        WHERE e.membre_id = ?";
    
$params = [$user_id];
$conditions = [];

// Filtre par statut
if ($statut !== 'tous') {
    if ($statut === 'en_cours') {
        $conditions[] = "e.statut = 'en_cours'";
    } elseif ($statut === 'retournes') {
        $conditions[] = "e.statut = 'retourne'";
    } elseif ($statut === 'en_retard') {
        $conditions[] = "e.statut = 'en_cours' AND e.date_retour_prevue < CURDATE()";
    }
}

// Filtre par date
if (!empty($date_debut)) {
    $conditions[] = "e.date_emprunt >= ?";
    $params[] = $date_debut;
}

if (!empty($date_fin)) {
    $conditions[] = "e.date_emprunt <= ?";
    $params[] = $date_fin;
}

// Ajouter les conditions
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

// Ajouter le tri
$tri_valides = ['date_emprunt', 'date_retour_prevue', 'date_retour_effective', 'titre'];
$ordre_valides = ['ASC', 'DESC'];

$tri = in_array($tri, $tri_valides) ? $tri : 'date_emprunt';
$ordre = in_array(strtoupper($ordre), $ordre_valides) ? strtoupper($ordre) : 'DESC';

$sql .= " ORDER BY $tri $ordre";

// Exécuter la requête
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$emprunts = $stmt->fetchAll();

// Statistiques
$stats = [
    'total' => 0,
    'en_cours' => 0,
    'retournes' => 0,
    'en_retard' => 0
];

foreach ($emprunts as $emprunt) {
    $stats['total']++;
    if ($emprunt['statut'] === 'retourne') {
        $stats['retournes']++;
    } elseif ($emprunt['statut'] === 'en_cours') {
        $stats['en_cours']++;
        if (strtotime($emprunt['date_retour_prevue']) < time()) {
            $stats['en_retard']++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon historique d'emprunts - Bibliotech</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <div class="section-card">
            <div class="section-header">
                <h1 class="section-title">
                    <i class="fas fa-history"></i> Mon historique d'emprunts
                </h1>
            </div>
            
            <div class="card-body" style="padding: 2rem;">
                <!-- Statistiques -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-book"></i>
                        <h3><?= $stats['total'] ?></h3>
                        <p>Emprunts totaux</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-clock"></i>
                        <h3><?= $stats['en_cours'] ?></h3>
                        <p>En cours</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-check-circle"></i>
                        <h3><?= $stats['retournes'] ?></h3>
                        <p>Retournés</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3><?= $stats['en_retard'] ?></h3>
                        <p>En retard</p>
                    </div>
                </div>
                
                <!-- Filtres -->
                <form method="GET" class="filter-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="statut">Statut</label>
                            <select id="statut" name="statut" class="form-control">
                                <option value="tous" <?= $statut === 'tous' ? 'selected' : '' ?>>Tous les emprunts</option>
                                <option value="en_cours" <?= $statut === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                                <option value="retournes" <?= $statut === 'retournes' ? 'selected' : '' ?>>Retournés</option>
                                <option value="en_retard" <?= $statut === 'en_retard' ? 'selected' : '' ?>>En retard</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_debut">Date de début</label>
                            <input type="date" id="date_debut" name="date_debut" class="form-control" 
                                   value="<?= htmlspecialchars($date_debut) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_fin">Date de fin</label>
                            <input type="date" id="date_fin" name="date_fin" class="form-control" 
                                   value="<?= htmlspecialchars($date_fin) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="tri">Trier par</label>
                            <select id="tri" name="tri" class="form-control">
                                <option value="date_emprunt" <?= $tri === 'date_emprunt' ? 'selected' : '' ?>>Date d'emprunt</option>
                                <option value="date_retour_prevue" <?= $tri === 'date_retour_prevue' ? 'selected' : '' ?>>Date de retour</option>
                                <option value="titre" <?= $tri === 'titre' ? 'selected' : '' ?>>Titre du livre</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                        <a href="historique_emprunt.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Réinitialiser
                        </a>
                    </div>
                </form>
                
                <!-- Liste des emprunts -->
                <?php if (empty($emprunts)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Aucun emprunt trouvé avec ces critères.
                    </div>
                <?php else: ?>
                    <div class="emprunts-list">
                        <?php foreach ($emprunts as $emprunt): 
                            $en_retard = $emprunt['statut'] === 'en_cours' && strtotime($emprunt['date_retour_prevue']) < time();
                        ?>
                            <div class="emprunt-card <?= $en_retard ? 'overdue' : '' ?>">
                                <div class="emprunt-header">
                                    <div class="book-info">
                                        <div class="book-cover-small">
                                            <?php if ($emprunt['couverture_url']): ?>
                                                <img src="<?= htmlspecialchars($emprunt['couverture_url']) ?>" alt="<?= htmlspecialchars($emprunt['titre']) ?>">
                                            <?php else: ?>
                                                <i class="fas fa-book"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="book-details">
                                            <h4><?= htmlspecialchars($emprunt['titre']) ?></h4>
                                            <p class="author"><?= htmlspecialchars($emprunt['auteur']) ?></p>
                                            <p class="code-barre">
                                                <i class="fas fa-barcode"></i> <?= htmlspecialchars($emprunt['code_barre']) ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="emprunt-status">
                                        <?php if ($emprunt['statut'] === 'retourne'): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle"></i> Retourné
                                            </span>
                                        <?php elseif ($en_retard): ?>
                                            <span class="badge badge-danger">
                                                <i class="fas fa-exclamation-triangle"></i> En retard (<?= $emprunt['jours_retard'] ?> jours)
                                            </span>
                                        <?php elseif ($emprunt['statut'] === 'en_cours'): ?>
                                            <span class="badge badge-info">
                                                <i class="fas fa-clock"></i> En cours
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">
                                                <i class="fas fa-question-circle"></i> <?= htmlspecialchars($emprunt['statut']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="emprunt-details">
                                    <div class="detail-row">
                                        <div class="detail-item">
                                            <span class="detail-label">Date d'emprunt:</span>
                                            <span class="detail-value"><?= date('d/m/Y', strtotime($emprunt['date_emprunt'])) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Retour prévu:</span>
                                            <span class="detail-value"><?= date('d/m/Y', strtotime($emprunt['date_retour_prevue'])) ?></span>
                                        </div>
                                        <?php if ($emprunt['statut'] === 'retourne' && $emprunt['date_retour_effective']): ?>
                                            <div class="detail-item">
                                                <span class="detail-label">Date de retour:</span>
                                                <span class="detail-value"><?= date('d/m/Y', strtotime($emprunt['date_retour_effective'])) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($emprunt['prolongations'] > 0): ?>
                                            <div class="detail-item">
                                                <span class="detail-label">Prolongations:</span>
                                                <span class="detail-value"><?= $emprunt['prolongations'] ?>/<?= $emprunt['prolongation_max'] ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($emprunt['notes'])): ?>
                                        <div class="notes">
                                            <span class="detail-label">Notes:</span>
                                            <p><?= htmlspecialchars($emprunt['notes']) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>