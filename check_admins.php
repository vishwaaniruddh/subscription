<?php
require_once __DIR__ . '/vendor/autoload.php';
use App\Database;

try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT id, username, password_hash, full_name FROM admins");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Admins found: " . count($admins) . "\n";
    foreach ($admins as $admin) {
        echo "ID: {$admin['id']} | Username: {$admin['username']} | Name: {$admin['full_name']}\n";
        // Check if 'password' matches the hash
        if (password_verify('password', $admin['password_hash'])) {
            echo "  - Password 'password' is CORRECT for this user.\n";
        } else {
            echo "  - Password 'password' is INCORRECT for this user.\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
