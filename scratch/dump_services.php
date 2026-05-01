<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT id, project_id, service_type, user_limit, active_user_count FROM services");
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($services, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
