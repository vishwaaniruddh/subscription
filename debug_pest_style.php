<?php

require 'vendor/autoload.php';

use App\Repositories\ServiceRepository;
use App\Services\ServiceManager;

function getTestDatabase(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();

        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $db = $_ENV['DB_DATABASE'] ?? 'subscription_db';
        $user = $_ENV['DB_USERNAME'] ?? 'reporting';
        $pass = $_ENV['DB_PASSWORD'] ?? 'reporting';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;port=$port;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $pdo = new PDO($dsn, $user, $pass, $options);
    }
    
    return $pdo;
}

$faker = Faker\Factory::create();
$pdo = getTestDatabase();
$repository = new ServiceRepository($pdo);
$serviceManager = new ServiceManager($repository);

// Get database's current date
$stmt = $pdo->query("SELECT CURDATE() as today");
$dbToday = $stmt->fetch(PDO::FETCH_ASSOC)['today'];

echo "Database today: $dbToday\n";

// Clean up test data
$pdo->exec("DELETE FROM services WHERE project_id >= 9100 AND project_id < 9200");
$pdo->exec("DELETE FROM projects WHERE id >= 9100 AND id < 9200");

// Ensure test client exists
$pdo->exec("INSERT IGNORE INTO clients (id, name, contact_info) VALUES (1, 'Test Client', 'test@example.com')");

// Create test projects for foreign key constraint
for ($i = 0; $i < 100; $i++) {
    $pdo->exec("INSERT INTO projects (id, client_id, name, description) VALUES (" . (9100 + $i) . ", 1, 'Test Project $i', 'Test')");
}

// Run 100 iterations
for ($i = 0; $i < 100; $i++) {
    $daysAhead = $faker->numberBetween(1, 30);
    
    // Create a service expiring today (using database's today)
    $serviceData = [
        'project_id' => 9100 + $i,
        'service_type' => $faker->randomElement(['web', 'mobile', 'other']),
        'user_limit' => $faker->numberBetween(10, 1000),
        'active_user_count' => $faker->numberBetween(0, 100),
        'start_date' => date('Y-m-d', strtotime($dbToday . ' -1 year')),
        'end_date' => $dbToday,
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date)
        VALUES (:project_id, :service_type, :user_limit, :active_user_count, :start_date, :end_date)
    ");
    $stmt->execute($serviceData);
    $serviceId = (int) $pdo->lastInsertId();
    
    // Query for expiring services
    $results = $serviceManager->getExpiringServices($daysAhead);
    $resultIds = array_map(fn($s) => $s->id, $results);
    
    // Verify service expiring today is included
    if (!in_array($serviceId, $resultIds)) {
        echo "✗ FAILED at iteration $i: Service $serviceId expiring today not found when querying for services expiring within $daysAhead days\n";
        echo "  Result IDs: " . implode(', ', $resultIds) . "\n";
        echo "  Service end_date: {$serviceData['end_date']}\n";
        echo "  Days ahead: $daysAhead\n";
        
        // Debug query
        $stmt = $pdo->prepare('SELECT id, end_date, DATEDIFF(end_date, CURDATE()) as days_until_expiry FROM services WHERE id = :id');
        $stmt->execute(['id' => $serviceId]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($service) {
            echo "  Service in DB: ID={$service['id']}, end_date={$service['end_date']}, days_until_expiry={$service['days_until_expiry']}\n";
        } else {
            echo "  Service NOT FOUND in database!\n";
        }
        
        break;
    }
    
    // Clean up this iteration's services
    $pdo->exec("DELETE FROM services WHERE project_id = " . (9100 + $i));
}

if ($i == 100) {
    echo "✓ All 100 iterations passed!\n";
}

// Clean up
$pdo->exec("DELETE FROM services WHERE project_id >= 9100 AND project_id < 9200");
$pdo->exec("DELETE FROM projects WHERE id >= 9100 AND id < 9200");
