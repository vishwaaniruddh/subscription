<?php
require_once __DIR__ . '/src/Database.php';
$db = \App\Database::getInstance();
$stmt = $db->query("DESCRIBE users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
