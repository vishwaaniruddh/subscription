<?php

require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$pdo = new PDO('mysql:host=localhost;dbname=subscription_db', 'reporting', 'reporting');

// Check autocommit status
$stmt = $pdo->query('SELECT @@autocommit');
$autocommit = $stmt->fetchColumn();
echo "Autocommit: $autocommit\n";

// Check if we're in a transaction
echo "In transaction: " . ($pdo->inTransaction() ? 'yes' : 'no') . "\n";
