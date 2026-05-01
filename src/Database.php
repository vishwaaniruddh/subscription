<?php

namespace App;

use PDO;
use PDOException;
use Dotenv\Dotenv;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
            $dotenv->safeLoad();

            $httpHost = $_SERVER['HTTP_HOST'] ?? '';
            $isLocal = (strpos($httpHost, 'localhost') !== false || strpos($httpHost, '127.0.0.1') !== false);

            if (!$isLocal && $httpHost !== '') {
                // Server Credentials
                $host = 'localhost';
                $db   = 'u444388293_subscription';
                $user = 'u444388293_subscription';
                $pass = 'AVav@@2026';
                $port = '3306';
            } else {
                // Local Credentials (from .env)
                $host = $_ENV['DB_HOST'] ?? 'localhost';
                $db   = $_ENV['DB_DATABASE'] ?? 'subscription_db';
                $user = $_ENV['DB_USERNAME'] ?? 'reporting';
                $pass = $_ENV['DB_PASSWORD'] ?? 'reporting';
                $port = $_ENV['DB_PORT'] ?? '3306';
            }
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$db;port=$port;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }

        return self::$instance;
    }
}
