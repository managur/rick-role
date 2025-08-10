#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Database Setup Script for Rick-Role
 *
 * This script creates the necessary database tables for the Rick-Role RBAC system.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Dotenv\Dotenv;

// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    (Dotenv::createImmutable(__DIR__ . '/../'))->load();
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

// Entity paths
$paths = [__DIR__ . '/../src/Entity'];
$isDevMode = ($_ENV['APP_ENV'] ?? 'dev') === 'dev';

// Create ORM configuration
$config = ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode);

// Create EntityManager
$entityManager = new EntityManager(DriverManager::getConnection($dbParams), $config);

// Create schema
$schemaTool = new \Doctrine\ORM\Tools\SchemaTool($entityManager);
$metadata = $entityManager->getMetadataFactory()->getAllMetadata();

echo "Creating database schema...\n";

try {
    $schemaTool->createSchema($metadata);
    echo "Database schema created successfully!\n";
} catch (\Exception $e) {
    echo "Error creating schema: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Database setup complete!\n"; 