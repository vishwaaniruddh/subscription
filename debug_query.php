<?php

require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$pdo = new PDO('mysql:host=localhost;dbname=subscription_db', 'reporting', 'reporting');
$stmt = $pdo->query('SELECT CURDATE() as today');
$today = $stmt->fetch(PDO::FETCH_ASSOC)['today'];
echo 'Database today: ' . $today . PHP_EOL;

// Create a test service expiring today
$pdo->exec('DELETE FROM services WHERE project_id = 99999');
$pdo->exec('DELETE FROM projects WHERE id = 99999');
$pdo->exec('INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, "Test", "test@test.com")');
$pdo->exec('INSERT INTO projects (id, client_id, name, description) VALUES (99999, 1, "Test", "Test")');
$pdo->exec("INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date) VALUES (99999, 'web', 100, 50, '2023-01-01', '$today')");

$serviceId = $pdo->lastInsertId();
echo "Created service ID: $serviceId with end_date: $today" . PHP_EOL;

// Query for services expiring within 30 days
$stmt = $pdo->prepare('SELECT * FROM services WHERE end_date >= CURDATE() AND end_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)');
$stmt->execute(['days' => 30]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo 'Found ' . count($results) . ' services' . PHP_EOL;
$found = false;
foreach ($results as $r) {
    if ($r['id'] == $serviceId) {
        $found = true;
        echo "✓ Found our test service! ID: {$r['id']}, end_date: {$r['end_date']}" . PHP_EOL;
    }
}

if (!$found) {
    echo "✗ Our test service was NOT found in results!" . PHP_EOL;
}

// Cleanup
$pdo->exec('DELETE FROM services WHERE project_id = 99999');
$pdo->exec('DELETE FROM projects WHERE id = 99999');
