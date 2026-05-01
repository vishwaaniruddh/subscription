<?php
require_once __DIR__ . '/../vendor/autoload.php';
use App\Database;

try {
    $db = Database::getInstance();
    $stmt = $db->query("DESCRIBE services");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
