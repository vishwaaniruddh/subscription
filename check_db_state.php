<?php

$pdo = new PDO('mysql:host=localhost;dbname=subscription_db', 'reporting', 'reporting');

$stmt = $pdo->query('SELECT COUNT(*) as count FROM services');
$count = $stmt->fetchColumn();
echo "Total services in database: $count\n";

$stmt = $pdo->query('SELECT COUNT(*) as count FROM services WHERE project_id >= 9000');
$count = $stmt->fetchColumn();
echo "Test services (project_id >= 9000): $count\n";

$stmt = $pdo->query('SELECT CURDATE() as today');
$today = $stmt->fetchColumn();
echo "Database today: $today\n";

$stmt = $pdo->query("SELECT COUNT(*) as count FROM services WHERE end_date = '$today'");
$count = $stmt->fetchColumn();
echo "Services expiring today: $count\n";
