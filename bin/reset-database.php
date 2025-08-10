#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Database Reset Script for Rick-Role
 *
 * Drops ORM-managed tables using Doctrine SchemaTool.
 * Intended to be followed by migrations to rebuild schema.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Dotenv\Dotenv;

// Load .env
if (file_exists(__DIR__ . '/../.env')) {
    (Dotenv::createImmutable(__DIR__ . '/../'))->load();
}

// Register UUID type
if (Type::hasType('uuid') === false) {
    Type::addType('uuid', \Ramsey\Uuid\Doctrine\UuidType::class);
}

// Database configuration
/** @var array<string, mixed> $dbParams */
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
$isDevMode = (($_ENV['APP_ENV'] ?? 'dev') === 'dev');

// Create ORM configuration
$config = ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode);

// Create EntityManager
$entityManager = new EntityManager(DriverManager::getConnection($dbParams), $config);

// Drop schema
$schemaTool = new SchemaTool($entityManager);
$metadata = $entityManager->getMetadataFactory()->getAllMetadata();

echo "Dropping ORM-managed tables...\n";
try {
    $schemaTool->dropSchema($metadata);
    echo "Schema dropped successfully.\n";
} catch (\Throwable $e) {
    fwrite(STDERR, 'Warning: ' . $e->getMessage() . "\n");
}

// Drop migrations tracking table so migrations can re-run from scratch
try {
    /** @var array<string, mixed> $migrationsConfig */
    $migrationsConfig = require __DIR__ . '/../migrations.php';
    $tableName = $migrationsConfig['table_storage']['table_name'] ?? 'doctrine_migration_versions';
    $connection = $entityManager->getConnection();
    $platform = $connection->getDatabasePlatform();
    $dropSql = 'DROP TABLE IF EXISTS ' . $platform->quoteIdentifier((string) $tableName);
    echo sprintf("Dropping migrations table '%s'...\n", (string) $tableName);
    $connection->executeStatement($dropSql);
    echo "Migrations table dropped.\n";
} catch (\Throwable $e) {
    fwrite(STDERR, 'Warning (migrations table): ' . $e->getMessage() . "\n");
}

echo "Database reset complete.\n";


