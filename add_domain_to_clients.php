<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Database;

try {
    $db = Database::getInstance();
    
    // Check if domain column already exists
    $stmt = $db->query("SHOW COLUMNS FROM clients LIKE 'domain'");
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        echo "Adding 'domain' column to clients table...\n";
        $db->exec("ALTER TABLE clients ADD COLUMN domain VARCHAR(255) AFTER name");
        echo "✓ Domain column added successfully!\n";
    } else {
        echo "✓ Domain column already exists.\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
