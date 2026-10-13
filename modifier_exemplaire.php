<?php
session_start();
require_once 'connexion.php';

// Vérifier l'authentification (staff seulement)
if (!isset($_SESSION['user_id']) || $_SESSION['type'] !== 'staff') {
    header('Location: login.php');
    exit;
}

// Récupérer l'ID de l'exemplaire
$exemplaire_id = $_GET['id'] ?? 0;

if (!$exemplaire_id) {
    header('Location: liste_livres.php?error=ID exemplaire manquant');
    exit;
}

// Récupérer les informations de l'exemplaire
try {
    $stmt = $pdo->prepare("
        SELECT e.*, l.titre, l.auteur 
        FROM exemplaires e
        JOIN livres l ON e.livre_id = l.id
        WHERE e.id = ?
    ");
    $stmt->execute([$exemplaire_id]);
    $exemplaire = $stmt->fetch();
    
    if (!$exemplaire) {
        header('Location: liste_livres.php?error=Exemplaire non trouvé');
        exit;
    }
    
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

// Traitement du formulaire
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code_barre = $_POST['code_barre'] ?? '';
    $code_inventaire = $_POST['code_inventaire'] ?? '';
    $etat = $_POST['etat'] ?? 'bon';
    $localisation = $_POST['localisation'] ?? '';
    $section = $_POST['section'] ?? '';
    $rayon = $_POST['rayon'] ?? '';
    $etagere = $_POST['etagere'] ?? '';
    $position = $_POST['position'] ?? '';
    $statut = $_POST['statut'] ?? 'disponible';
    $notes = $_POST['notes'] ?? '';
    
    // Validation
    if (empty($code_barre)) {
        $message = 'Le code-barre est obligatoire';
        $message_type = 'error';
    } else {
        try {
            // Mettre à jour l'exemplaire
            $update_stmt = $pdo->prepare("
                UPDATE exemplaires SET
                    code_barre = ?,
                    code_inventaire = ?,
                    etat = ?,
                    localisation = ?,
                    section = ?,
                    rayon = ?,
                    etagere = ?,
                    position = ?,
                    statut = ?,
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $update_stmt->execute([
                $code_barre,
                $code_inventaire,
                $etat,
                $localisation,
                $section,
                $rayon,
                $etagere,
                $position,
                $statut,
                $notes,
                $exemplaire_id
            ]);
            
            // Journaliser l'action
            $log_stmt = $pdo->prepare("
                INSERT INTO logs_audit (table_name, record_id, action, user_id, user_type)
                VALUES ('exemplaires', ?, 'UPDATE', ?, 'staff')
            ");
            $log_stmt->execute([$exemplaire_id, $_SESSION['user_id']]);
            
            $message = 'Exemplaire modifié avec succès';
            $message_type = 'success';
            
            // Recharger les données
            $stmt->execute([$exemplaire_id]);
            $exemplaire = $stmt->fetch();
            
        } catch (PDOException $e) {
            $message = 'Erreur : ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Exemplaire - Bibliotech</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #2d5a87 0%, #152a45 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 800px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(to right, #007bff, #0056b3);
            color: white;
            padding: 25px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .book-info {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .book-title {
            font-size: 1.4rem;
            color: #333;
            margin-bottom: 5px;
        }
        
        .book-author {
            color: #666;
            font-style: italic;
        }
        
        .form-container {
            padding: 30px;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #007bff;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #28a745;
            color: white;
        }
        
        .btn-primary:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .status-disponible { background: #d4edda; color: #155724; }
        .status-emprunte { background: #cce5ff; color: #004085; }
        .status-reserve { background: #fff3cd; color: #856404; }
        .status-reparation { background: #d1ecf1; color: #0c5460; }
        .status-perdu { background: #f8d7da; color: #721c24; }
        
        .state-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .state-neuf { background: #28a745; color: white; }
        .state-tres_bon { background: #20c997; color: white; }
        .state-bon { background: #17a2b8; color: white; }
        .state-moyen { background: #ffc107; color: #212529; }
        .state-mauvais { background: #fd7e14; color: white; }
        .state-hors_service { background: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-book"></i> Modifier l'exemplaire</h1>
            <p>Bibliotech - Gestion des exemplaires</p>
        </div>
        
        <div class="book-info">
            <div class="book-title"><?= htmlspecialchars($exemplaire['titre']) ?></div>
            <div class="book-author"><?= htmlspecialchars($exemplaire['auteur']) ?></div>
            <div style="margin-top: 10px; font-size: 0.9em; color: #666;">
                <strong>ID :</strong> <?= $exemplaire['id'] ?> | 
                <strong>Livre ID :</strong> <?= $exemplaire['livre_id'] ?>
            </div>
        </div>
        
        <div class="form-container">
            <?php if ($message): ?>
                <div class="message <?= $message_type ?>">
                    <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="code_barre">
                            <i class="fas fa-barcode"></i> Code-barre *
                        </label>
                        <input type="text" id="code_barre" name="code_barre" 
                               value="<?= htmlspecialchars($exemplaire['code_barre']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="code_inventaire">
                            <i class="fas fa-hashtag"></i> Code inventaire
                        </label>
                        <input type="text" id="code_inventaire" name="code_inventaire" 
                               value="<?= htmlspecialchars($exemplaire['code_inventaire'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="etat">
                            <i class="fas fa-heart"></i> État
                            <span class="state-badge state-<?= $exemplaire['etat'] ?>">
                                <?= $exemplaire['etat'] ?>
                            </span>
                        </label>
                        <select id="etat" name="etat" required>
                            <option value="neuf" <?= $exemplaire['etat'] == 'neuf' ? 'selected' : '' ?>>Neuf</option>
                            <option value="tres_bon" <?= $exemplaire['etat'] == 'tres_bon' ? 'selected' : '' ?>>Très bon</option>
                            <option value="bon" <?= $exemplaire['etat'] == 'bon' ? 'selected' : '' ?>>Bon</option>
                            <option value="moyen" <?= $exemplaire['etat'] == 'moyen' ? 'selected' : '' ?>>Moyen</option>
                            <option value="mauvais" <?= $exemplaire['etat'] == 'mauvais' ? 'selected' : '' ?>>Mauvais</option>
                            <option value="hors_service" <?= $exemplaire['etat'] == 'hors_service' ? 'selected' : '' ?>>Hors service</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="statut">
                            <i class="fas fa-info-circle"></i> Statut
                            <span class="status-badge status-<?= $exemplaire['statut'] ?>">
                                <?= $exemplaire['statut'] ?>
                            </span>
                        </label>
                        <select id="statut" name="statut" required>
                            <option value="disponible" <?= $exemplaire['statut'] == 'disponible' ? 'selected' : '' ?>>Disponible</option>
                            <option value="emprunte" <?= $exemplaire['statut'] == 'emprunte' ? 'selected' : '' ?>>Emprunté</option>
                            <option value="reserve" <?= $exemplaire['statut'] == 'reserve' ? 'selected' : '' ?>>Réservé</option>
                            <option value="en_reparation" <?= $exemplaire['statut'] == 'en_reparation' ? 'selected' : '' ?>>En réparation</option>
                            <option value="perdu" <?= $exemplaire['statut'] == 'perdu' ? 'selected' : '' ?>>Perdu</option>
                            <option value="retire" <?= $exemplaire['statut'] == 'retire' ? 'selected' : '' ?>>Retiré</option>
                            <option value="exclu" <?= $exemplaire['statut'] == 'exclu' ? 'selected' : '' ?>>Exclu</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="localisation">
                            <i class="fas fa-map-marker-alt"></i> Localisation
                        </label>
                        <input type="text" id="localisation" name="localisation" 
                               value="<?= htmlspecialchars($exemplaire['localisation'] ?? '') ?>"
                               placeholder="Ex: Rez-de-chaussée, 1er étage...">
                    </div>
                    
                    <div class="form-group">
                        <label for="section">
                            <i class="fas fa-th-large"></i> Section
                        </label>
                        <input type="text" id="section" name="section" 
                               value="<?= htmlspecialchars($exemplaire['section'] ?? '') ?>"
                               placeholder="Ex: Littérature, Sciences...">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="rayon">
                            <i class="fas fa-columns"></i> Rayon
                        </label>
                        <input type="text" id="rayon" name="rayon" 
                               value="<?= htmlspecialchars($exemplaire['rayon'] ?? '') ?>"
                               placeholder="Ex: R, S, H...">
                    </div>
                    
                    <div class="form-group">
                        <label for="etagere">
                            <i class="fas fa-layer-group"></i> Étagère
                        </label>
                        <input type="text" id="etagere" name="etagere" 
                               value="<?= htmlspecialchars($exemplaire['etagere'] ?? '') ?>"
                               placeholder="Ex: 1, 2, 3...">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="position">
                        <i class="fas fa-map-pin"></i> Position sur l'étagère
                    </label>
                    <input type="text" id="position" name="position" 
                           value="<?= htmlspecialchars($exemplaire['position'] ?? '') ?>"
                           placeholder="Ex: A, B, C... ou 1, 2, 3...">
                </div>
                
                <div class="form-group">
                    <label for="notes">
                        <i class="fas fa-sticky-note"></i> Notes
                    </label>
                    <textarea id="notes" name="notes" 
                              placeholder="Notes supplémentaires sur cet exemplaire..."><?= htmlspecialchars($exemplaire['notes'] ?? '') ?></textarea>
                </div>
                
                <!-- Informations de suivi -->
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
                    <h3 style="margin-bottom: 10px; color: #495057;">
                        <i class="fas fa-history"></i> Informations de suivi
                    </h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; font-size: 0.9em;">
                        <div>
                            <strong>Date d'acquisition :</strong><br>
                            <?= $exemplaire['date_acquisition'] ? date('d/m/Y', strtotime($exemplaire['date_acquisition'])) : 'Non spécifiée' ?>
                        </div>
                        <div>
                            <strong>Prix d'acquisition :</strong><br>
                            <?= $exemplaire['prix_acquisition'] ? number_format($exemplaire['prix_acquisition'], 2) . ' €' : 'Non spécifié' ?>
                        </div>
                        <div>
                            <strong>Dernier inventaire :</strong><br>
                            <?= $exemplaire['date_dernier_inventaire'] ? date('d/m/Y', strtotime($exemplaire['date_dernier_inventaire'])) : 'Jamais' ?>
                        </div>
                        <div>
                            <strong>Créé le :</strong><br>
                            <?= date('d/m/Y H:i', strtotime($exemplaire['created_at'])) ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="details_livre.php?id=<?= $exemplaire['livre_id'] ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Retour au livre
                    </a>
                    
                    <div>
                        <a href="supprimer_exemplaire.php?id=<?= $exemplaire['id'] ?>" 
                           class="btn btn-danger"
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet exemplaire ?');">
                            <i class="fas fa-trash"></i> Supprimer
                        </a>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Mettre à jour les badges en temps réel
        document.getElementById('etat').addEventListener('change', function() {
            const badge = document.querySelector('.state-badge');
            badge.className = 'state-badge state-' + this.value;
            badge.textContent = this.options[this.selectedIndex].text;
        });
        
        document.getElementById('statut').addEventListener('change', function() {
            const badge = document.querySelector('.status-badge');
            badge.className = 'status-badge status-' + this.value;
            badge.textContent = this.options[this.selectedIndex].text;
        });
        
        // Confirmation avant soumission
        document.querySelector('form').addEventListener('submit', function(e) {
            const codeBarre = document.getElementById('code_barre').value;
            if (!codeBarre.trim()) {
                e.preventDefault();
                alert('Le code-barre est obligatoire');
                document.getElementById('code_barre').focus();
            }
        });
    </script>
    <style>
    .notifications-bell {
        position: relative;
        cursor: pointer;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.2s;
    }

    .notifications-bell:hover {
        background: var(--primary);
        color: white;
    }

    .notification-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: var(--danger);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 12px;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .notifications-dropdown {
        position: absolute;
        top: 60px;
        right: 20px;
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        box-shadow: var(--shadow-md);
        width: 320px;
        max-height: 400px;
        overflow-y: auto;
        z-index: 1000;
    }

    .notifications-dropdown ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .notifications-dropdown li {
        padding: 12px 16px;
        border-bottom: 1px solid var(--gray-200);
    }

    .notifications-dropdown li.non-lue {
        background: rgba(233, 236, 239, 0.5);
        font-weight: 500;
    }

    .notifications-dropdown li a {
        text-decoration: none;
        color: var(--gray-700);
        display: block;
    }

    .notifications-dropdown li small {
        color: var(--gray-500);
        font-size: 0.85rem;
    }

    .dropdown-footer {
        padding: 12px;
        text-align: center;
        border-top: 1px solid var(--gray-200);
    }

    .dropdown-footer a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
    }
</style>

<script>
    function toggleNotifications() {
        const dropdown = document.getElementById('notifications-dropdown');
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    }

    // Fermer le dropdown si clic ailleurs
    document.addEventListener('click', function(e) {
        const bell = document.querySelector('.notifications-bell');
        const dropdown = document.getElementById('notifications-dropdown');
        if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

    // Marquer comme lu (appel AJAX simple - à améliorer si besoin)
    function marquerLue(id) {
        fetch('marquer_lue.php?id=' + id)
            .then(() => {
                // Option : rafraîchir le badge ou supprimer la classe non-lue
            });
    }
</script>
</body>
</html>