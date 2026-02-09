<?php
session_start();
require_once 'connexion.php';

// Protection staff uniquement
if (!isset($_SESSION['user_id']) || 
    !in_array($_SESSION['role'], ['bibliothecaire', 'admin', 'super_admin'])) {
    header("Location: index.php");
    exit;
}

try {
    $membres = $pdo->query("
        SELECT 
            m.id,
            m.numero_membre,
            m.nom,
            m.prenom,
            m.email,
            m.telephone
        FROM membres m
        ORDER BY m.nom ASC, m.prenom ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Erreur lors du chargement des membres : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des membres – Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #2b848e;
            --primary-dark: #1e6a72;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #254c5b;
            --gray: #e9ecef;
            --radius: 10px;
            --shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        body { margin:0; font-family:system-ui,sans-serif; background:var(--light); color:var(--dark); padding:2rem 1rem; }
        .container { max-width:1450px; margin:0 auto; }
        h1 { color:var(--primary); text-align:center; margin-bottom:2rem; }
        table { width:100%; border-collapse:collapse; background:white; border-radius:var(--radius); overflow:hidden; box-shadow:var(--shadow); }
        th, td { padding:14px 16px; text-align:left; border-bottom:1px solid var(--gray); }
        th { background:var(--primary); color:white; font-weight:600; }
        tr:hover { background:#f0f8f8; }
        .actions { white-space:nowrap; }
        .btn {
            padding:7px 12px;
            border:none;
            border-radius:6px;
            color:white;
            font-size:0.95rem;
            cursor:pointer;
            margin:0 3px;
            transition:all 0.2s;
        }
        .btn-details  { background:#6c757d; }
        .btn-edit     { background:#007bff; }
        .btn-amendes  { background:#fd7e14; }
        .btn-history  { background:#17a2b8; }
        .btn-toggle   { background:#6f42c1; }
        .btn-delete   { background:var(--danger); }
        .btn:hover    { opacity:0.92; transform:scale(1.04); }
        @media (max-width: 992px) {
            .actions { display:flex; flex-wrap:wrap; gap:6px; justify-content:center; }
            .btn { margin:4px 2px; }
        }
        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr { display:block; }
            thead tr { position:absolute; top:-9999px; left:-9999px; }
            tr { border:1px solid var(--gray); margin-bottom:1.5rem; border-radius:var(--radius); }
            td { border:none; position:relative; padding-left:50%; min-height:2.8rem; }
            td:before {
                position:absolute; top:14px; left:16px; width:45%; padding-right:10px;
                white-space:nowrap; font-weight:bold; color:var(--dark);
            }
            td:nth-of-type(1):before { content:"N° membre"; }
            td:nth-of-type(2):before { content:"Nom"; }
            td:nth-of-type(3):before { content:"Email"; }
            td:nth-of-type(4):before { content:"Téléphone"; }
            td:nth-of-type(5):before { content:"Actions"; }
        }
    </style>
</head>
<body>

<div class="container">
    <h1><i class="fas fa-users"></i> Liste des membres</h1>

    <?php if (isset($error)): ?>
        <div style="background:#f8d7da; color:#721c24; padding:1rem; border-radius:var(--radius); margin-bottom:1.5rem; text-align:center;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($membres)): ?>
        <div class="empty-state">
            <i class="fas fa-user-slash fa-3x" style="opacity:0.4; margin-bottom:1rem;"></i><br>
            Aucun membre inscrit pour le moment.
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>N° membre</th>
                    <th>Nom Prénom</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($membres as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['numero_membre'] ?? '—') ?></td>
                        <td><strong><?= htmlspecialchars($m['nom'] . ' ' . $m['prenom']) ?></strong></td>
                        <td><?= htmlspecialchars($m['email'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($m['telephone'] ?: '—') ?></td>
                        <td class="actions">
                            <button class="btn btn-details" 
                                    onclick="location.href='voir_membre.php?id=<?= $m['id'] ?>'">
                                <i class="fas fa-eye"></i> Détails
                            </button>
                            
                            <button class="btn btn-edit" 
                                    onclick="location.href='modifier_membre.php?id=<?= $m['id'] ?>'">
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                            
                            <button class="btn btn-amendes" 
                                    onclick="location.href='gerer_amendes_membre.php?id=<?= $m['id'] ?>'">
                                <i class="fas fa-coins"></i> Amendes
                            </button>
                            
                            <button class="btn btn-history" 
                                    onclick="location.href='historique_emprunts_membre.php?id=<?= $m['id'] ?>'">
                                <i class="fas fa-history"></i> Historique
                            </button>
                            
                            <button class="btn btn-delete" 
                                    onclick="if(confirm('SUPPRIMER DÉFINITIVEMENT <?= addslashes($m['nom'].' '.$m['prenom']) ?> ?\nCette action est irréversible.')) location.href='supprimer_membre.php?id=<?= $m['id'] ?>';">
                                <i class="fas fa-trash-alt"></i> Supprimer
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="back-link">
        <a href="admin_dashboard.php">← Retour au tableau de bord admin</a>
    </div>
</div>

</body>
</html>