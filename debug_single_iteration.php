<?php

require 'vendor/autoload.php';

use App\Repositories\ServiceRepository;
use App\Services\ServiceManager;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$pdo = new PDO('mysql:host=localhost;dbname=subscription_db', 'reporting', 'reporting');
$repository = new ServiceRepository($pdo);
$serviceManager = new ServiceManager($repository);

$faker = Faker\Factory::create();

$stmt = $pdo->query('SELECT CURDATE() as today');
$dbToday = $stmt->fetch(PDO::FETCH_ASSOC)['today'];
echo "Database today: $dbToday\n";

// Clean up
$pdo->exec('DELETE FROM services WHERE project_id >= 9100 AND project_id < 9200');
$pdo->exec('DELETE FROM projects WHERE id >= 9100 AND id < 9200');
$pdo->exec('INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, "Test Client", "test@example.com")');

// Create test project
$pdo->exec('INSERT INTO projects (id, client_id, name, description) VALUES (9100, 1, "Test Project 0", "Test")');

// Run one iteration
$i = 0;
$daysAhead = $faker->numberBetween(1, 30);
echo "Days ahead: $daysAhead\n";

// Create a service expiring today
$serviceData = [
    'project_id' => 9100 + $i,
    'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
    'user_limit' => $faker->numberBetween(10, 1000),
    'active_user_count' => $faker->numberBetween(0, 100),
    'start_date' => date('Y-m-d', strtotime($dbToday . ' -1 year')),
    'end_date' => $dbToday,
];

echo "Creating service with end_date: {$serviceData['end_date']}\n";

$stmt = $pdo->prepare("
    INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date)
    VALUES (:project_id, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
");
$stmt->execute($serviceData);
$serviceId = (int) $pdo->lastInsertId();

echo "Created service ID: $serviceId\n";

// Query for expiring services
$results = $serviceManager->getExpiringServices($daysAhead);
$resultIds = array_map(fn($s) => $s->id, $results);

echo "Found " . count($results) . " services\n";
echo "Result IDs: " . implode(', ', $resultIds) . "\n";

if (in_array($serviceId, $resultIds)) {
    echo "✓ Service $serviceId IS in the results!\n";
} else {
    echo "✗ Service $serviceId is NOT in the results!\n";
    echo "Expected to find service expiring today ($dbToday) when querying for services expiring within $daysAhead days\n";
    
    // Debug: show what the query would return
    $stmt = $pdo->prepare('SELECT id, end_date FROM services WHERE end_date >= CURDATE() AND end_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)');
    $stmt->execute(['days' => $daysAhead]);
    $debugResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Direct query results:\n";
    foreach ($debugResults as $r) {
        echo "  ID: {$r['id']}, end_date: {$r['end_date']}\n";
    }
}

// Clean up
$pdo->exec('DELETE FROM services WHERE project_id = 9100');
$pdo->exec('DELETE FROM projects WHERE id = 9100');
