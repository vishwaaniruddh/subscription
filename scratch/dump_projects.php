<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT id, name, api_key, domain FROM projects");
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($projects, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
