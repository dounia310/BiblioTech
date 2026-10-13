<?php
session_start();
require_once 'connexion.php';

// Vérifier si staff
if(!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'bibliothecaire' && $_SESSION['role'] != 'admin' && $_SESSION['role'] != 'super_admin')) {
    header("Location: index.php");
    exit();
}

$message = '';
$error = '';

// NOUVEL EMPRUNT
if(isset($_POST['nouvel_emprunt'])) {
    $membre_id = $_POST['membre_id'];
    $exemplaire_id = $_POST['exemplaire_id'];
    
    // Vérifier si exemplaire disponible
    $check_exemplaire = $pdo->prepare("SELECT id FROM exemplaires WHERE id = ? AND statut = 'disponible'");
    $check_exemplaire->execute([$exemplaire_id]);
    
    if($check_exemplaire->fetch()) {
        // Vérifier membre
        $check_membre = $pdo->prepare("SELECT id FROM membres WHERE id = ? AND statut = 'actif'");
        $check_membre->execute([$membre_id]);
        
        if($check_membre->fetch()) {
            try {
                // Créer emprunt
                $stmt = $pdo->prepare("
                    INSERT INTO emprunts (membre_id, exemplaire_id, date_emprunt, date_retour_prevue, statut, utilisateur_id)
                    VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'en_cours', ?)
                ");
                $stmt->execute([$membre_id, $exemplaire_id, $_SESSION['user_id']]);
                
                // Mettre à jour exemplaire
                $pdo->prepare("UPDATE exemplaires SET statut = 'emprunte' WHERE id = ?")->execute([$exemplaire_id]);
                
                $message = "Emprunt créé avec succès !";
            } catch(Exception $e) {
                $error = "Erreur: " . $e->getMessage();
            }
        } else {
            $error = "Membre non trouvé ou inactif";
        }
    } else {
        $error = "Exemplaire non disponible";
    }
}

// RETOURNER LIVRE
if(isset($_POST['retourner'])) {
    $emprunt_id = $_POST['emprunt_id'];
    
    try {
        // Récupérer exemplaire
        $get_exemplaire = $pdo->prepare("SELECT exemplaire_id FROM emprunts WHERE id = ?");
        $get_exemplaire->execute([$emprunt_id]);
        $exemplaire_id = $get_exemplaire->fetchColumn();
        
        if($exemplaire_id) {
            // Marquer retourné
            $pdo->prepare("UPDATE emprunts SET date_retour_effective = CURDATE(), statut = 'retourne' WHERE id = ?")->execute([$emprunt_id]);
            
            // Remettre exemplaire disponible
            $pdo->prepare("UPDATE exemplaires SET statut = 'disponible' WHERE id = ?")->execute([$exemplaire_id]);
            
            $message = "Livre retourné avec succès";
        }
    } catch(Exception $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// RÉCUPÉRER LES DONNÉES
try {
    $emprunts = $pdo->query("
        SELECT 
            e.id as emprunt_id,
            e.date_emprunt,
            e.date_retour_prevue,
            e.statut,
            m.nom as membre_nom,
            m.prenom as membre_prenom,
            m.numero_membre,
            m.email as membre_email,
            l.titre as livre_titre,
            l.auteur,
            l.isbn,
            ex.code_barre
        FROM emprunts e
        JOIN membres m ON e.membre_id = m.id
        JOIN exemplaires ex ON e.exemplaire_id = ex.id
        JOIN livres l ON ex.livre_id = l.id
        WHERE e.statut = 'en_cours'
        ORDER BY e.date_retour_prevue
    ")->fetchAll();
} catch(Exception $e) {
    $emprunts = [];
    $error = "Erreur emprunts: " . $e->getMessage();
}

try {
    $exemplaires = $pdo->query("
        SELECT 
            ex.id as exemplaire_id,
            ex.code_barre,
            l.id as livre_id,
            l.titre,
            l.auteur,
            l.isbn,
            l.annee_publication
        FROM exemplaires ex
        JOIN livres l ON ex.livre_id = l.id
        WHERE ex.statut = 'disponible'
        ORDER BY l.titre, ex.code_barre
    ")->fetchAll();
} catch(Exception $e) {
    $exemplaires = [];
    $error = "Erreur exemplaires: " . $e->getMessage();
}

try {
    $membres = $pdo->query("
        SELECT id, nom, prenom, numero_membre, email, type_membre, statut
        FROM membres 
        WHERE statut = 'actif'
        ORDER BY nom, prenom
    ")->fetchAll();
} catch(Exception $e) {
    $membres = [];
    $error = "Erreur membres: " . $e->getMessage();
}

// Statistiques
$stats_emprunts = count($emprunts);
$stats_exemplaires = count($exemplaires);
$stats_membres = count($membres);
$stats_retards = 0;
foreach($emprunts as $e) {
    if(strtotime($e['date_retour_prevue']) < time()) {
        $stats_retards++;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Emprunts - BiblioTech</title>
    
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
            --info: #3b82f6;
            
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            
            --radius: 10px;
            --radius-full: 9999px;
            
            --transition: 250ms ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-100);
            color: var(--gray-700);
            line-height: 1.6;
        }

        /* HEADER */
        .admin-header {
            background: var(--light);
            border-bottom: 1px solid var(--gray-200);
            padding: 16px 30px;
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .admin-header-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-header-title i {
            color: var(--accent);
            font-size: 1.5rem;
        }

        .breadcrumb {
            font-size: 0.9rem;
            color: var(--gray-500);
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
            transition: color var(--transition);
        }

        .breadcrumb a:hover {
            color: var(--primary-light);
        }

        .admin-header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: var(--gray-100);
            border-radius: var(--radius-full);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-full);
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-info strong {
            font-size: 0.9rem;
            color: var(--gray-700);
        }

        .user-info span {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        /* MAIN CONTAINER */
        .admin-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* ALERTS */
        .alert {
            padding: 16px 24px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInUp 0.3s ease-out;
        }

        .alert-success {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 1px solid #bbf7d0;
            color: #14532d;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fff5f5 0%, #fee2e2 100%);
            border: 1px solid #fecaca;
            color: #7f1d1d;
        }

        .alert i {
            font-size: 1.2rem;
        }

        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--light);
            border-radius: var(--radius);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            box-shadow: var(--shadow-md);
            transition: all var(--transition);
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-4px);
        }

        .stat-card.danger::before {
            background: var(--danger);
        }

        .stat-card.warning::before {
            background: var(--warning);
        }

        .stat-card.success::before {
            background: var(--success);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--gray-500);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            background: var(--gray-100);
        }

        .stat-card.danger .stat-icon {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger);
        }

        .stat-card.warning .stat-icon {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .stat-card.success .stat-icon {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .stat-value {
            font-size: 2.4rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }

        .stat-card.danger .stat-value {
            color: var(--danger);
        }

        .stat-card.warning .stat-value {
            color: var(--warning);
        }

        .stat-card.success .stat-value {
            color: var(--success);
        }

        .stat-change {
            font-size: 0.85rem;
            color: var(--gray-500);
        }

        /* CONTENT SECTIONS */
        .content-section {
            background: var(--light);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            margin-bottom: 30px;
            animation: slideInUp 0.5s ease-out;
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

        .section-title i {
            color: var(--accent);
            font-size: 1.4rem;
        }

        .section-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* FORM STYLES */
        .form-container {
            padding: 30px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-label {
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.95rem;
        }

        .form-select {
            padding: 12px 16px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius);
            font-size: 1rem;
            color: var(--gray-700);
            background: var(--light);
            transition: all var(--transition);
        }

        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 58, 95, 0.1);
        }

        .form-select option {
            padding: 10px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: var(--radius);
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success) 0%, #34d399 100%);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #059669 0%, var(--success) 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger) 0%, #ef4444 100%);
            color: white;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #b91c1c 0%, var(--danger) 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        /* TABLES */
        .table-responsive {
            overflow-x: auto;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        .admin-table thead {
            background: var(--gray-100);
            border-bottom: 2px solid var(--gray-200);
        }

        .admin-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .admin-table td {
            padding: 16px;
            border-bottom: 1px solid var(--gray-200);
        }

        .admin-table tbody tr {
            transition: all var(--transition);
        }

        .admin-table tbody tr:hover {
            background: var(--gray-100);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.en-cours {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }

        .status-badge.en-retard {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger);
        }

        .status-badge.retourne {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .member-info {
            display: flex;
            flex-direction: column;
        }

        .member-name {
            font-weight: 600;
            color: var(--gray-700);
        }

        .member-id {
            font-size: 0.85rem;
            color: var(--gray-500);
        }

        .book-info {
            display: flex;
            flex-direction: column;
        }

        .book-title {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 4px;
        }

        .book-details {
            font-size: 0.85rem;
            color: var(--gray-500);
        }

        /* EMPTY STATES */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 3.5rem;
            opacity: 0.4;
            margin-bottom: 20px;
        }

        /* QUICK ACTIONS */
        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
        }

        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .admin-container {
                padding: 0 15px;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 16px;
            }

            .stat-value {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                gap: 15px;
                padding: 12px 15px;
            }

            .admin-header-left,
            .admin-header-right {
                width: 100%;
                justify-content: center;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .admin-table {
                font-size: 0.85rem;
            }

            .admin-table th,
            .admin-table td {
                padding: 12px 8px;
            }
        }

        @media (max-width: 480px) {
            .stat-card {
                padding: 16px;
            }

            .stat-value {
                font-size: 1.4rem;
            }

            .section-title {
                font-size: 1rem;
            }
        }

        /* ANIMATIONS */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: var(--radius-full);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <div class="admin-header">
        <div class="admin-header-left">
            <div class="admin-header-title">
                <i class="fas fa-exchange-alt"></i>
                Gestion des Emprunts
            </div>
            <div class="breadcrumb">
                <a href="admin_dashboard.php"><i class="fas fa-home"></i> Tableau de bord</a>
                <span>/</span>
                <span>Emprunts</span>
            </div>
        </div>

        <div class="admin-header-right">
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'B', 0, 1)); ?>
                </div>
                <div class="user-info">
                    <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Bibliothécaire'); ?></strong>
                    <span><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Staff')); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTAINER -->
    <div class="admin-container">

        <!-- ALERTS -->
        <?php if($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <strong><?php echo $message; ?></strong>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <strong><?php echo $error; ?></strong>
            </div>
        <?php endif; ?>

        <!-- STATISTICS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Emprunts en cours</div>
                        <div class="stat-value"><?php echo $stats_emprunts; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-book-reader"></i>
                    </div>
                </div>
                <div class="stat-change">Actuellement empruntés</div>
            </div>

            <div class="stat-card danger">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Emprunts en retard</div>
                        <div class="stat-value"><?php echo $stats_retards; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                </div>
                <div class="stat-change">Attention requise</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Exemplaires disponibles</div>
                        <div class="stat-value"><?php echo $stats_exemplaires; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                </div>
                <div class="stat-change">Prêts à être empruntés</div>
            </div>

            <div class="stat-card success">
                <div class="stat-header">
                    <div>
                        <div class="stat-label">Membres actifs</div>
                        <div class="stat-value"><?php echo $stats_membres; ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-change">Peuvent emprunter</div>
            </div>
        </div>

        <!-- NOUVEL EMPRUNT FORM -->
        <div class="content-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-plus-circle"></i>
                    Nouvel Emprunt
                </div>
                <div class="section-badge">
                    Durée: 14 jours
                </div>
            </div>

            <div class="form-container">
                <?php if($stats_exemplaires == 0 || $stats_membres == 0): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>ATTENTION:</strong>
                            <?php if($stats_exemplaires == 0): ?>
                                <p>Aucun exemplaire disponible. <a href="ajouter_livre.php">Ajouter des livres</a> ou <a href="ajouter_exemplaire.php">ajouter des exemplaires</a>.</p>
                            <?php endif; ?>
                            <?php if($stats_membres == 0): ?>
                                <p>Aucun membre actif. <a href="ajouter_membre.php">Ajouter des membres</a>.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user"></i>
                                    Membre
                                </label>
                                <select name="membre_id" class="form-select" required>
                                    <option value="">-- Sélectionner un membre --</option>
                                    <?php foreach($membres as $m): ?>
                                    <option value="<?php echo $m['id']; ?>">
                                        <?php echo htmlspecialchars($m['nom'] . ' ' . $m['prenom']); ?> 
                                        (<?php echo htmlspecialchars($m['numero_membre']); ?> - <?php echo htmlspecialchars($m['type_membre']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-book"></i>
                                    Exemplaire
                                </label>
                                <select name="exemplaire_id" class="form-select" required>
                                    <option value="">-- Sélectionner un exemplaire --</option>
                                    <?php foreach($exemplaires as $ex): ?>
                                    <option value="<?php echo $ex['exemplaire_id']; ?>">
                                        <?php echo htmlspecialchars($ex['titre']); ?> 
                                        - <?php echo htmlspecialchars($ex['auteur']); ?>
                                        (Code: <?php echo htmlspecialchars($ex['code_barre']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit" name="nouvel_emprunt" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Créer l'emprunt
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- EMPRUNTS EN COURS -->
        <div class="content-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-clock"></i>
                    Emprunts en Cours
                </div>
                <div class="section-badge">
                    <?php echo $stats_emprunts; ?> emprunt(s)
                </div>
            </div>

            <div class="table-responsive">
                <?php if($stats_emprunts > 0): ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Membre</th>
                                <th>Livre</th>
                                <th>Code barre</th>
                                <th>Date emprunt</th>
                                <th>Retour prévu</th>
                                <th>Statut</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($emprunts as $e): 
                                $retard = strtotime($e['date_retour_prevue']) < time();
                                $jours = $retard ? floor((time() - strtotime($e['date_retour_prevue'])) / 86400) : 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="member-info">
                                        <span class="member-name"><?php echo htmlspecialchars($e['membre_nom'] . ' ' . $e['membre_prenom']); ?></span>
                                        <span class="member-id"><?php echo htmlspecialchars($e['numero_membre']); ?></span>
                                        <small><?php echo htmlspecialchars($e['membre_email']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="book-info">
                                        <span class="book-title"><?php echo htmlspecialchars($e['livre_titre']); ?></span>
                                        <span class="book-details"><?php echo htmlspecialchars($e['auteur']); ?> - ISBN: <?php echo htmlspecialchars($e['isbn']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <code style="background: var(--gray-100); padding: 4px 8px; border-radius: 4px;">
                                        <?php echo htmlspecialchars($e['code_barre']); ?>
                                    </code>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($e['date_emprunt'])); ?>
                                </td>
                                <td>
                                    <div>
                                        <?php echo date('d/m/Y', strtotime($e['date_retour_prevue'])); ?>
                                        <?php if($retard): ?>
                                            <div style="color: var(--danger); font-weight: 600; margin-top: 4px;">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Retard: <?php echo $jours; ?> jour(s)
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $retard ? 'en-retard' : 'en-cours'; ?>">
                                        <?php echo $retard ? 'En retard' : 'En cours'; ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="emprunt_id" value="<?php echo $e['emprunt_id']; ?>">
                                        <button type="submit" name="retourner" class="btn btn-success" style="padding: 8px 16px;">
                                            <i class="fas fa-check"></i>
                                            Retourner
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <h3>Aucun emprunt en cours</h3>
                        <p>Tous les livres sont disponibles à l'emprunt</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- EXEMPLAIRES DISPONIBLES -->
        <div class="content-section">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-book"></i>
                    Exemplaires Disponibles
                </div>
                <div class="section-badge">
                    <?php echo $stats_exemplaires; ?> disponible(s)
                </div>
            </div>

            <div class="table-responsive">
                <?php if($stats_exemplaires > 0): ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Livre</th>
                                <th>Auteur</th>
                                <th>ISBN</th>
                                <th>Code barre</th>
                                <th>Année</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($exemplaires as $ex): ?>
                            <tr>
                                <td>
                                    <div class="book-info">
                                        <span class="book-title"><?php echo htmlspecialchars($ex['titre']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($ex['auteur']); ?></td>
                                <td>
                                    <code style="background: var(--gray-100); padding: 4px 8px; border-radius: 4px;">
                                        <?php echo htmlspecialchars($ex['isbn']); ?>
                                    </code>
                                </td>
                                <td>
                                    <code style="background: var(--gray-100); padding: 4px 8px; border-radius: 4px;">
                                        <?php echo htmlspecialchars($ex['code_barre']); ?>
                                    </code>
                                </td>
                                <td><?php echo htmlspecialchars($ex['annee_publication']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <h3>Aucun exemplaire disponible</h3>
                        <p>Tous les livres sont actuellement empruntés</p>
                        <div class="quick-actions">
                            <a href="ajouter_livre.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Ajouter un livre
                            </a>
                            <a href="ajouter_exemplaire.php" class="btn btn-outline">
                                <i class="fas fa-copy"></i>
                                Ajouter un exemplaire
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

       
            </div>
        </div>

    </div>

    <script>
        // Ajout d'interactivité
        document.addEventListener('DOMContentLoaded', function() {
            // Animation pour les boutons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Confirmation pour les retours
            const retourButtons = document.querySelectorAll('button[name="retourner"]');
            retourButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    if(!confirm('Confirmer le retour de ce livre ?')) {
                        e.preventDefault();
                    }
                });
            });

            // Auto-select pour les formulaires
            const selectElements = document.querySelectorAll('select');
            selectElements.forEach(select => {
                select.addEventListener('focus', function() {
                    this.style.boxShadow = '0 0 0 3px rgba(30, 58, 95, 0.1)';
                });
                select.addEventListener('blur', function() {
                    this.style.boxShadow = 'none';
                });
            });
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