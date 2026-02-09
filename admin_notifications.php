<?php
$pdo = new PDO("mysql:host=localhost;dbname=bibliotech;charset=utf8mb4", "root", "");
$stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC");
$notifs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head><title>Notifications</title></head>
<body>
    <h1>Notifications</h1>
    <?php if ($notifs): ?>
        <ul>
        <?php foreach ($notifs as $n): ?>
            <li><?= htmlspecialchars($n['created_at']) ?> : <?= htmlspecialchars($n['message']) ?></li>
        <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Aucune notification.</p>
    <?php endif; ?>
</body>
</html>