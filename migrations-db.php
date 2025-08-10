<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Dotenv\Dotenv;

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    (Dotenv::createImmutable(__DIR__))->load();
}

// Register UUID type
if (!Type::hasType('uuid')) {
    Type::addType('uuid', \Ramsey\Uuid\Doctrine\UuidType::class);
}

// Database configuration
$dbParams = [
    'driver' => $_ENV['DB_DRIVER'] ?? 'pdo_mysql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => $_ENV['DB_PORT'] ?? 3306,
    'dbname' => $_ENV['DB_NAME'] ?? 'rick_role',
    'user' => $_ENV['DB_USER'] ?? 'rick_role',
    'password' => $_ENV['DB_PASS'] ?? 'secure_password',
    'charset' => 'utf8mb4',
];

// Create connection
$connection = DriverManager::getConnection($dbParams);

return $connection; 