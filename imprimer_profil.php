<?php
session_start();
require_once 'connexion.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Vérifier si l'ID est passé
if (!isset($_GET['id'])) {
    die("Erreur : ID du membre non spécifié");
}

$membre_id = intval($_GET['id']);

try {
    // Récupérer les infos du membre
    $stmt = $pdo->prepare("SELECT * FROM membres WHERE id = ?");
    $stmt->execute([$membre_id]);
    $membre = $stmt->fetch();
    
    if (!$membre) {
        die("Erreur : Membre non trouvé");
    }
    
    // Récupérer les emprunts en cours
    $stmt = $pdo->prepare("
        SELECT e.*, l.titre, l.auteur 
        FROM emprunts e
        JOIN exemplaires ex ON e.exemplaire_id = ex.id
        JOIN livres l ON ex.livre_id = l.id
        WHERE e.membre_id = ? AND e.statut = 'en_cours'
        ORDER BY e.date_retour_prevue
    ");
    $stmt->execute([$membre_id]);
    $emprunts = $stmt->fetchAll();
    
    // Récupérer les amendes impayées
    $stmt = $pdo->prepare("
        SELECT * FROM amendes 
        WHERE membre_id = ? AND statut IN ('impaye', 'partiel')
        ORDER BY date_creation DESC
    ");
    $stmt->execute([$membre_id]);
    $amendes = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Membre - <?= htmlspecialchars($membre['nom']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        
        .print-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .print-header h1 {
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .print-date {
            color: #666;
            font-size: 0.9em;
        }
        
        .section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        
        .section-title {
            background: #3498db;
            color: white;
            padding: 10px;
            border-radius: 5px 5px 0 0;
            margin-bottom: 0;
        }
        
        .section-content {
            border: 1px solid #ddd;
            border-top: none;
            padding: 15px;
            border-radius: 0 0 5px 5px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: bold;
            color: #2c3e50;
            display: inline-block;
            width: 150px;
        }
        
        .info-value {
            color: #333;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        table th {
            background: #f8f9fa;
            padding: 10px;
            text-align: left;
            border-bottom: 2px solid #ddd;
        }
        
        table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        table tr:hover {
            background: #f9f9f9;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .total {
            text-align: right;
            font-weight: bold;
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .print-actions {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin: 0 10px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-print {
            background: #3498db;
            color: white;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        @media print {
            .print-actions {
                display: none;
            }
            
            body {
                margin: 0;
                font-size: 12pt;
            }
            
            .section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- En-tête -->
    <div class="print-header">
        <h1>Bibliotech - Profil Membre</h1>
        <p class="print-date">Imprimé le : <?= date('d/m/Y à H:i') ?></p>
    </div>
    
    <!-- Informations personnelles -->
    <div class="section">
        <h2 class="section-title">Informations Personnelles</h2>
        <div class="section-content">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Numéro membre :</span>
                    <span class="info-value"><?= htmlspecialchars($membre['numero_membre']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Nom complet :</span>
                    <span class="info-value"><?= htmlspecialchars($membre['prenom'] . ' ' . $membre['nom']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email :</span>
                    <span class="info-value"><?= htmlspecialchars($membre['email']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Téléphone :</span>
                    <span class="info-value"><?= htmlspecialchars($membre['telephone'] ?? 'Non renseigné') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Type :</span>
                    <span class="info-value"><?= htmlspecialchars($membre['type_membre']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date d'inscription :</span>
                    <span class="info-value"><?= date('d/m/Y', strtotime($membre['date_inscription'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date d'expiration :</span>
                    <span class="info-value">
                        <?= date('d/m/Y', strtotime($membre['date_expiration'])) ?>
                        <?php 
                        $jours_restants = floor((strtotime($membre['date_expiration']) - time()) / (60 * 60 * 24));
                        if ($jours_restants < 0) {
                            echo '<span class="badge badge-danger">Expiré</span>';
                        } elseif ($jours_restants < 30) {
                            echo '<span class="badge badge-warning">' . $jours_restants . ' jours</span>';
                        } else {
                            echo '<span class="badge badge-success">Valide</span>';
                        }
                        ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Statut :</span>
                    <span class="info-value">
                        <?php
                        $statut_class = [
                            'actif' => 'badge-success',
                            'inactif' => 'badge-warning',
                            'suspendu' => 'badge-danger',
                            'expire' => 'badge-warning'
                        ];
                        $class = $statut_class[$membre['statut']] ?? 'badge-warning';
                        ?>
                        <span class="badge <?= $class ?>"><?= htmlspecialchars($membre['statut']) ?></span>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Emprunts en cours -->
    <div class="section">
        <h2 class="section-title">Emprunts en cours</h2>
        <div class="section-content">
            <?php if (count($emprunts) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Livre</th>
                            <th>Auteur</th>
                            <th>Emprunté le</th>
                            <th>Retour prévu</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emprunts as $emprunt): ?>
                        <tr>
                            <td><?= htmlspecialchars($emprunt['titre']) ?></td>
                            <td><?= htmlspecialchars($emprunt['auteur']) ?></td>
                            <td><?= date('d/m/Y', strtotime($emprunt['date_emprunt'])) ?></td>
                            <td>
                                <?= date('d/m/Y', strtotime($emprunt['date_retour_prevue'])) ?>
                                <?php 
                                $jours_retard = floor((time() - strtotime($emprunt['date_retour_prevue'])) / (60 * 60 * 24));
                                if ($jours_retard > 0) {
                                    echo '<span class="badge badge-danger">' . $jours_retard . ' jours de retard</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($emprunt['statut'] == 'en_retard'): ?>
                                    <span class="badge badge-danger">En retard</span>
                                <?php else: ?>
                                    <span class="badge badge-success">En cours</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="total">Total : <?= count($emprunts) ?> emprunt(s)</div>
            <?php else: ?>
                <p style="text-align: center; color: #666;">Aucun emprunt en cours</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Amendes impayées -->
    <div class="section">
        <h2 class="section-title">Amendes impayées</h2>
        <div class="section-content">
            <?php if (count($amendes) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Montant</th>
                            <th>Montant payé</th>
                            <th>Date</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_du = 0;
                        foreach ($amendes as $amende): 
                            $restant = $amende['montant'] - $amende['montant_paye'];
                            $total_du += $restant;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($amende['description']) ?></td>
                            <td><?= number_format($amende['montant'], 2) ?> €</td>
                            <td><?= number_format($amende['montant_paye'], 2) ?> €</td>
                            <td><?= date('d/m/Y', strtotime($amende['date_creation'])) ?></td>
                            <td>
                                <?php if ($amende['statut'] == 'partiel'): ?>
                                    <span class="badge badge-warning">Partiel</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Impayé</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="total">Total dû : <?= number_format($total_du, 2) ?> €</div>
            <?php else: ?>
                <p style="text-align: center; color: #666;">Aucune amende impayée</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Résumé -->
    <div class="section">
        <h2 class="section-title">Résumé</h2>
        <div class="section-content">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Quota emprunt :</span>
                    <span class="info-value"><?= $membre['quota_emprunt'] ?> livres</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Quota réservation :</span>
                    <span class="info-value"><?= $membre['quota_reservation'] ?> livres</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Catégorie tarif :</span>
                    <span class="info-value"><?= htmlspecialchars($membre['categorie_tarif']) ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Boutons d'action -->
    <div class="print-actions">
        <button class="btn btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimer
        </button>
        <a href="liste_membres.php" class="btn btn-back">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>
    
    <script>
        // Ajouter Font Awesome
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
        document.head.appendChild(link);
        
        // Auto-print optionnel (décommenter si vous voulez l'impression automatique)
        // window.onload = function() {
        //     window.print();
        // };
    </script>
</body>
</html>