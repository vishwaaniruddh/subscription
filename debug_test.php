<?php

require 'vendor/autoload.php';

use App\Repositories\ServiceRepository;
use App\Services\ServiceManager;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$pdo = new PDO('mysql:host=localhost;dbname=subscription_db', 'reporting', 'reporting');
$repository = new ServiceRepository($pdo);
$serviceManager = new ServiceManager($repository);

$stmt = $pdo->query('SELECT CURDATE() as today');
$dbToday = $stmt->fetch(PDO::FETCH_ASSOC)['today'];
echo "Database today: $dbToday\n";

// Clean up
$pdo->exec('DELETE FROM services WHERE project_id = 99999');
$pdo->exec('DELETE FROM projects WHERE id = 99999');
$pdo->exec('INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, "Test", "test@test.com")');
$pdo->exec('INSERT INTO projects (id, client_id, name, description) VALUES (99999, 1, "Test", "Test")');

// Create a service expiring today
$serviceData = [
    'project_id' => 99999,
    'service_type' => 'web',
    'user_limit' => 100,
    'active_user_count' => 50,
    'start_date' => date('Y-m-d', strtotime($dbToday . ' -1 year')),
    'end_date' => $dbToday,
];

$stmt = $pdo->prepare("
    INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date)
    VALUES (:project_id, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
");
$stmt->execute($serviceData);
$serviceId = (int) $pdo->lastInsertId();

echo "Created service ID: $serviceId with end_date: {$serviceData['end_date']}\n";

// Query using ServiceManager
$daysAhead = 5;
$results = $serviceManager->getExpiringServices($daysAhead);
$resultIds = array_map(fn($s) => $s->id, $results);

echo "Query for services expiring within $daysAhead days\n";
echo "Found " . count($results) . " services\n";
echo "Result IDs: " . implode(', ', $resultIds) . "\n";

if (in_array($serviceId, $resultIds)) {
    echo "✓ Our test service IS in the results!\n";
} else {
    echo "✗ Our test service is NOT in the results!\n";
}

// Clean up
$pdo->exec('DELETE FROM services WHERE project_id = 99999');
$pdo->exec('DELETE FROM projects WHERE id = 99999');
