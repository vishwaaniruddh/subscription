<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

try {
    $db = Database::getInstance();
    $clients = $db->query("SELECT id, name FROM clients")->fetchAll(PDO::FETCH_ASSOC);
    echo "Clients: " . json_encode($clients, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
