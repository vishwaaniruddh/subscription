<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Database.php';
$db = \App\Database::getInstance();

try {
    $db->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL AFTER user_identifier");
    echo "Added email column.\n";
} catch (Exception $e) {
    echo "Email column might already exist: " . $e->getMessage() . "\n";
}

try {
    $db->exec("ALTER TABLE users ADD COLUMN name VARCHAR(255) NULL AFTER user_identifier");
    echo "Added name column.\n";
} catch (Exception $e) {
    echo "Name column might already exist: " . $e->getMessage() . "\n";
}
