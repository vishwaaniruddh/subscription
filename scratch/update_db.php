<?php
require_once __DIR__ . '/../src/Database.php';

use App\Database;

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Adding columns 'name' and 'email' to 'users' table...\n";
    
    $sql = "ALTER TABLE users 
            ADD COLUMN IF NOT EXISTS name VARCHAR(255) NULL AFTER user_identifier,
            ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL AFTER name";
    
    $db->exec($sql);
    
    echo "Successfully updated 'users' table schema.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
