<?php
require_once __DIR__ . '/vendor/autoload.php';
use App\Database;

try {
    $db = Database::getInstance();
    $newHash = password_hash('password', PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE admins SET password_hash = :hash WHERE username = 'admin'");
    $stmt->execute(['hash' => $newHash]);
    
    if ($stmt->rowCount() > 0) {
        echo "Successfully updated password for 'admin' to 'password'.\n";
    } else {
        echo "Could not update password. Maybe 'admin' user doesn't exist or already has that password hash (unlikely with salt).\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
