<?php

$pdo = new PDO('mysql:host=localhost;dbname=subscription_db', 'reporting', 'reporting');
$pdo->exec('DELETE FROM services');
$pdo->exec('DELETE FROM projects');
$pdo->exec('DELETE FROM clients WHERE id != 1');
echo "Cleaned up database\n";
