<?php
require_once 'connexion.php';
require_once 'header.php';

// Récupérer les événements à venir
$evenements = $pdo->query("
    SELECT e.*, 
           (SELECT COUNT(*) FROM inscriptions_evenement ie WHERE ie.evenement_id = e.id) as inscrits
    FROM evenements e 
    WHERE e.date_debut >= CURDATE() 
    AND e.statut IN ('planifie', 'confirme')
    ORDER BY e.date_debut ASC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC) ?? [];

// Événements passés
$evenements_passes = $pdo->query("
    SELECT * FROM evenements 
    WHERE date_debut < CURDATE() 
    ORDER BY date_debut DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC) ?? [];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Événements - Bibliotech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #2b848e;
            --primary-dark: #1e6a72;
            --secondary: #7ab781;
            --light: #f8f9fa;
            --dark: #254c5b;
            --radius: 10px;
            --shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .page-title {
            font-size: 2.2rem;
            color: var(--dark);
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--secondary);
        }
        
        .section-title {
            font-size: 1.8rem;
            color: var(--primary-dark);
            margin: 40px 0 20px;
        }
        
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .event-card {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.3s;
        }
        
        .event-card:hover {
            transform: translateY(-5px);
        }
        
        .event-image {
            height: 200px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .event-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .event-info {
            padding: 20px;
        }
        
        .event-date {
            background: var(--primary);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .event-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
            line-height: 1.3;
        }
        
        .event-details {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 15px 0;
            font-size: 0.9rem;
            color: #666;
        }
        
        .event-detail {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .event-description {
            color: #666;
            line-height: 1.5;
            margin: 15px 0;
            font-size: 0.95rem;
        }
        
        .event-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-event {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 0.9rem;
        }
        
        .btn-details {
            background: var(--light);
            color: var(--dark);
        }
        
        .btn-details:hover {
            background: #e9ecef;
        }
        
        .btn-register {
            background: var(--secondary);
            color: white;
        }
        
        .btn-register:hover {
            background: var(--primary-dark);
        }
        
        .btn-full {
            background: #ff6b6b;
            color: white;
        }
        
        .btn-full:hover {
            background: #ff5252;
        }
        
        .event-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .type-atelier { background: #e3f2fd; color: #1565c0; }
        .type-conference { background: #f3e5f5; color: #7b1fa2; }
        .type-club { background: #e8f5e9; color: #2e7d32; }
        .type-rencontre { background: #fff3e0; color: #ef6c00; }
        
        .calendar-view {
            display: grid;
            grid-template-columns: 100px 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .calendar-date {
            background: var(--primary);
            color: white;
            text-align: center;
            padding: 15px;
            border-radius: var(--radius);
        }
        
        .calendar-day {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .calendar-month {
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .calendar-event {
            background: white;
            padding: 15px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .empty-message {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .empty-message i {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #ddd;
        }
        
        .banner {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 40px;
            border-radius: var(--radius);
            margin-bottom: 40px;
            text-align: center;
        }
        
        .banner h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .events-grid {
                grid-template-columns: 1fr;
            }
            
            .calendar-view {
                grid-template-columns: 1fr;
            }
            
            .calendar-date {
                max-width: 100px;
            }
        }
    </style>
</head>
<body>


<div class="container">
    <div class="banner">
        <h1>Événements à la Bibliothèque</h1>
        <p>Participez à nos activités culturelles et rencontrez d'autres passionnés de lecture</p>
    </div>
    
    <h1 class="page-title">Prochains événements</h1>
    
    <?php if (empty($evenements)): ?>
        <div class="empty-message">
            <i class="fas fa-calendar"></i>
            <h3>Aucun événement à venir</h3>
            <p>Revenez bientôt pour découvrir notre programme d'activités</p>
        </div>
    <?php else: ?>
        <div class="events-grid">
            <?php foreach ($evenements as $event): 
                $date = new DateTime($event['date_debut']);
                $places_restantes = $event['capacite_max'] - $event['inscrits'];
                $type_class = 'type-' . str_replace('_', '-', $event['type']);
            ?>
                <div class="event-card">
                    <div class="event-image">
                        <?php if ($event['image_url']): ?>
                            <img src="<?= htmlspecialchars($event['image_url']) ?>" alt="<?= htmlspecialchars($event['titre']) ?>">
                        <?php else: ?>
                            <i class="fas fa-calendar-alt" style="font-size: 3rem; color: #ccc;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="event-info">
                        <div class="<?= $type_class ?> event-type">
                            <?= ucfirst(str_replace('_', ' ', $event['type'])) ?>
                        </div>
                        <div class="event-date">
                            <i class="far fa-calendar"></i> 
                            <?= $date->format('d/m/Y • H:i') ?>
                        </div>
                        
                        <h3 class="event-title"><?= htmlspecialchars($event['titre']) ?></h3>
                        
                        <div class="event-details">
                            <?php if ($event['lieu']): ?>
                                <div class="event-detail">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars($event['lieu']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($event['intervenant']): ?>
                                <div class="event-detail">
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($event['intervenant']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($event['capacite_max']): ?>
                                <div class="event-detail">
                                    <i class="fas fa-users"></i>
                                    <?= $event['inscrits'] ?>/<?= $event['capacite_max'] ?> inscrits
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <p class="event-description">
                            <?= htmlspecialchars(substr($event['description'] ?? '', 0, 120)) ?>...
                        </p>
                        
                        <div class="event-actions">
                            <button class="btn-event btn-details" onclick="showEventDetails(<?= $event['id'] ?>)">
                                <i class="fas fa-info-circle"></i> Détails
                            </button>
                            
                            <?php if ($event['inscription_requise']): ?>
                                <?php if ($places_restantes > 0): ?>
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                        <button class="btn-event btn-register" onclick="registerEvent(<?= $event['id'] ?>)">
                                            <i class="fas fa-user-plus"></i> S'inscrire
                                        </button>
                                    <?php else: ?>
                                        <a href="login.php?redirect=evenements.php" class="btn-event btn-register" style="text-decoration: none; text-align: center;">
                                            <i class="fas fa-sign-in-alt"></i> Se connecter
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn-event btn-full" disabled>
                                        <i class="fas fa-times"></i> Complet
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="btn-event btn-details">
                                    <i class="fas fa-door-open"></i> Entrée libre
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($evenements_passes)): ?>
        <h2 class="section-title">Événements passés</h2>
        <div class="events-grid">
            <?php foreach ($evenements_passes as $event): ?>
                <div class="event-card">
                    <div class="event-info">
                        <div class="event-date" style="background: #95a5a6;">
                            <i class="far fa-calendar"></i> 
                            <?= date('d/m/Y', strtotime($event['date_debut'])) ?>
                        </div>
                        
                        <h3 class="event-title"><?= htmlspecialchars($event['titre']) ?></h3>
                        <p class="event-description">
                            <?= htmlspecialchars(substr($event['description'] ?? '', 0, 150)) ?>...
                        </p>
                        
                        <div class="event-actions">
                            <button class="btn-event btn-details" onclick="showEventDetails(<?= $event['id'] ?>)">
                                <i class="fas fa-history"></i> Voir le résumé
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function showEventDetails(eventId) {
    window.location.href = 'details_evenement.php?id=' + eventId;
}

function registerEvent(eventId) {
    if (confirm('Confirmer votre inscription à cet événement ?')) {
        fetch('inscrire_evenement.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `event_id=${eventId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Inscription confirmée !');
                location.reload();
            } else {
                alert('Erreur : ' + data.message);
            }
        });
    }
}
</script>

<?php require_once 'footer.php'; ?>
</body>
</html>