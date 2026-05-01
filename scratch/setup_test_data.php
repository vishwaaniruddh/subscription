<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

try {
    $db = Database::getInstance();
    
    // Cleanup existing for clean run
    $db->exec("DELETE FROM services WHERE project_id IN (SELECT id FROM projects WHERE api_key = 'NbzLHFFYXgBkGLeO')");
    $db->exec("DELETE FROM projects WHERE api_key = 'NbzLHFFYXgBkGLeO'");
    $db->exec("DELETE FROM clients WHERE name = 'SARS SPL'");

    // 1. Create Client
    $db->exec("INSERT INTO clients (name) VALUES ('SARS SPL')");
    $clientId = $db->lastInsertId();
    echo "Created Client ID: $clientId\n";
    
    // 2. Create Project
    $stmt = $db->prepare("INSERT INTO projects (client_id, name, api_key, domain) VALUES (:client_id, :name, :api_key, :domain)");
    $stmt->execute([
        'client_id' => $clientId,
        'name' => 'Main Project',
        'api_key' => 'NbzLHFFYXgBkGLeO',
        'domain' => 'https://project.sarsspl.com'
    ]);
    $projectId = $db->lastInsertId();
    echo "Created Project ID: $projectId\n";
    
    // 3. Create Service (Subscription)
    $stmt = $db->prepare("INSERT INTO services (project_id, service_type, user_limit, active_user_count, start_date, end_date) VALUES (:project_id, :type, :limit, :count, :start, :end)");
    $stmt->execute([
        'project_id' => $projectId,
        'type' => 'Standard',
        'limit' => 100,
        'count' => 0,
        'start' => date('Y-m-d'),
        'end' => date('Y-m-d', strtotime('+1 year'))
    ]);
    echo "Created Service for Project ID: $projectId\n";
    
    echo "Setup complete!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
