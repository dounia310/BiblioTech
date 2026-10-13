<?php

require_once 'connexion.php';
require_once 'header.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['type'] ?? null) !== 'membre') {
    header('Location: login.php');
    exit;
}

$membre_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT 
        r.*,
        l.titre,
        COALESCE(a.nom_complet, CONCAT(a.nom, ' ', COALESCE(a.prenom, ''))) AS auteur,
        (
            SELECT COUNT(*) 
            FROM exemplaires ex 
            WHERE ex.livre_id = l.id 
            AND ex.statut = 'disponible'
        ) AS exemplaires_disponibles
    FROM reservations r
    JOIN livres l ON r.livre_id = l.id
    LEFT JOIN auteurs a ON l.auteur_id = a.id
    WHERE r.membre_id = ?
    ORDER BY r.date_reservation DESC
");
$stmt->execute([$membre_id]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
   
:root {
    --primary: #2b848e;        
    --primary-dark: #7ab781;
    --primary-light: #254c5b;
    --gray-100: #f9fafb;
    --gray-200: #e5e7eb;
    --gray-300: #d1d5db;
    --gray-500: #6b7280;
    --gray-700: #374151;
    --gray-800: #1f2937;
    --red-500: #ef4444;
    --green-500: #10b981;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
    --radius: 10px;
}

* {
    box-sizing: border-box;
}

body {
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    background: var(--gray-100);
    color: var(--gray-800);
    line-height: 1.55;
    margin: 0;
    padding: 0;
}

/* Container principal */
main, .container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 1.5rem 1rem;
}

/* Titre */
h1 {
    font-size: 1.9rem;
    font-weight: 600;
    color: var(--gray-800);
    margin: 1.5rem 0 1.8rem;
    padding-bottom: 0.6rem;
    border-bottom: 2px solid var(--primary);
    display: inline-block;
}

/* Tableau */
table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: white;
    box-shadow: var(--shadow-md);
    border-radius: var(--radius);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

thead {
    background: var(--primary);
    color: white;
}

th, td {
    padding: 14px 16px;
    text-align: left;
}

th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.82rem;
    letter-spacing: 0.4px;
}

tbody tr {
    border-bottom: 1px solid var(--gray-200);
    transition: background-color 0.12s ease;
}

tbody tr:hover {
    background-color: #f0f7ff;
}

tbody tr:last-child {
    border-bottom: none;
}

/* Cellules spécifiques */
td strong {
    color: var(--gray-800);
    font-weight: 600;
}

td small {
    color: var(--gray-500);
    font-size: 0.9rem;
}

td a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.15s ease;
}

td a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

/* Statut */
td:nth-child(4) {  /* colonne Statut */
    font-weight: 500;
    text-transform: capitalize;
}

/* Couleurs selon statut */
.statut-en_attente  { color: #d97706; }
.statut-disponible  { color: var(--green-500); font-weight: 600; }
.statut-terminee,
.statut-annulee     { color: var(--gray-500); }

/* Boutons d'action */
a[href*="recuperer_reservation"],
a[href*="annuler_reservation"] {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 0.92rem;
    transition: all 0.18s ease;
    margin-right: 0.4rem;
}

a[href*="recuperer_reservation"] {
    background: var(--green-500);
    color: white;
}

a[href*="recuperer_reservation"]:hover {
    background: #059669;
    transform: translateY(-1px);
}

a[href*="annuler_reservation"] {
    background: #fee2e2;
    color: #dc2626;
}

a[href*="annuler_reservation"]:hover {
    background: #fecaca;
    color: #b91c1c;
}

/* Lien retour */
p a[href*="membre_dashboard"] {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--primary);
    font-weight: 500;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 6px;
    transition: background 0.2s;
}

p a[href*="membre_dashboard"]:hover {
    background: rgba(37, 99, 235, 0.08);
}

/* Responsive */
@media (max-width: 768px) {
    table {
        font-size: 0.94rem;
    }
    
    th, td {
        padding: 12px 10px;
    }
    
    thead {
        display: none;
    }
    
    tr {
        display: block;
        margin-bottom: 1.2rem;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        box-shadow: var(--shadow-sm);
        background: white;
    }
    
    td {
        display: block;
        text-align: right;
        position: relative;
        padding-left: 50%;
        border: none;
    }
    
    td::before {
        content: attr(data-label);
        position: absolute;
        left: 14px;
        width: 45%;
        font-weight: 600;
        text-align: left;
        color: var(--gray-700);
    }
    
    td:first-child {
        font-weight: bold;
        text-align: center;
        padding: 16px 12px;
        border-bottom: 1px solid var(--gray-200);
    }
}

/* Petit message "aucune réservation" */
p:where(:not([style])):has(+ table + p) {
    font-size: 1.1rem;
    color: var(--gray-500);
    text-align: center;
    padding: 3rem 1rem;
}
</style>
</head>

    

<h1>Mes réservations</h1>

<?php if (empty($reservations)): ?>
    <p>Aucune réservation.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Livre</th>
                <th>Date</th>
                <th>Expiration</th>
                <th>Statut</th>
                <th>Dispo</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reservations as $r): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($r['titre']) ?></strong><br>
                    <small><?= htmlspecialchars($r['auteur'] ?? '') ?></small>
                </td>
                <td><?= date('d/m/Y H:i', strtotime($r['date_reservation'])) ?></td>
                <td><?= $r['date_expiration'] ? date('d/m/Y', strtotime($r['date_expiration'])) : '-' ?></td>
                <td><?= htmlspecialchars($r['statut']) ?></td>
                <td><?= (int)$r['exemplaires_disponibles'] ?></td>
                <td>
                    <?php if ($r['statut'] === 'disponible'): ?>
                        <a href="recuperer_reservation.php?id=<?= (int)$r['id'] ?>">Récupérer</a>
                    <?php endif; ?>
                    <?php if (in_array($r['statut'], ['en_attente','disponible'], true)): ?>
                        <?php if ($r['statut'] === 'disponible'): ?> | <?php endif; ?>
                        <a href="annuler_reservation.php?id=<?= (int)$r['id'] ?>" onclick="return confirm('Annuler cette réservation ?');">Annuler</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p style="margin-top:20px;"><a href="./membre_compte.php">← Retour à mon espace</a></p>

<?php require_once 'footer.php'; ?>

</body>
</html>