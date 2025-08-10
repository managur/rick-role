<?php

declare(strict_types=1);

namespace RickRole\CLI;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use RickRole\CLI\Command\PermissionCommand;
use RickRole\CLI\Command\RoleCommand;
use RickRole\CLI\Command\UserCommand;
use Symfony\Component\Console\Application;
use Dotenv\Dotenv;

/**
 * CLI Bootstrap for Rick-Role
 *
 * Sets up the Doctrine EntityManager and configures the CLI application.
 */
final class CLIBootstrap
{
    private static ?EntityManager $entityManager = null;

    /**
     * Get the Doctrine EntityManager instance.
     */
    public static function getEntityManager(): EntityManager
    {
        if (self::$entityManager === null) {
            self::$entityManager = self::createEntityManager();
        }

        return self::$entityManager;
    }

    /**
     * Create and configure the CLI application.
     */
    public static function createApplication(): Application
    {
        // Load .env
        if (file_exists(__DIR__ . '/../../.env')) {
            (Dotenv::createImmutable(__DIR__ . '/../../'))->load();
        }

        $entityManager = self::getEntityManager();

        $application = new Application('Rick-Role CLI', '1.0.0');

        $application->addCommands([
            new RoleCommand($entityManager),
            new UserCommand($entityManager),
            new PermissionCommand($entityManager),
        ]);

        return $application;
    }

    /**
     * Create the Doctrine EntityManager with database configuration.
     */
    private static function createEntityManager(): EntityManager
    {
        // Register UUID type
        if (!Type::hasType('uuid')) {
            Type::addType('uuid', \Ramsey\Uuid\Doctrine\UuidType::class);
        }

        /** @var array{driver: 'ibm_db2'|'mysqli'|'oci8'|'pdo_mysql'|'pdo_oci'|'pdo_pgsql'|'pdo_sqlite'|'pdo_sqlsrv'|'pgsql'|'sqlite3'|'sqlsrv', host: string, port: int, dbname: string, user: string, password: string, charset: 'utf8mb4'} $dbParams */
        $dbParams = [
            'driver' => self::getDatabaseDriver(),
            'host' => self::getEnvString('DB_HOST', 'localhost'),
            'port' => self::getEnvInt('DB_PORT', 3306),
            'dbname' => self::getEnvString('DB_NAME', 'rick_role'),
            'user' => self::getEnvString('DB_USER', 'rick_role'),
            'password' => self::getEnvString('DB_PASS', 'secure_password'),
            'charset' => 'utf8mb4',
        ];

        // Entity paths
        $paths = [__DIR__ . '/../Entity'];
        $isDevMode = ($_ENV['APP_ENV'] ?? 'dev') === 'dev';

        // Create ORM configuration
        $config = ORMSetup::createAttributeMetadataConfiguration($paths, $isDevMode);

        // Create EntityManager
        return new EntityManager(DriverManager::getConnection($dbParams), $config);
    }

    /**
     * Get environment variable as string with fallback.
     */
    private static function getEnvString(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }

    /**
     * Get environment variable as integer with fallback.
     */
    private static function getEnvInt(string $key, int $default): int
    {
        $value = $_ENV[$key] ?? $default;
        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Get database driver with validation.
     */
    private static function getDatabaseDriver(): string
    {
        $validDrivers = [
            'ibm_db2', 'mysqli', 'oci8', 'pdo_mysql', 'pdo_oci', 'pdo_pgsql',
            'pdo_sqlite', 'pdo_sqlsrv', 'pgsql', 'sqlite3', 'sqlsrv'
        ];

        $driver = self::getEnvString('DB_DRIVER', 'pdo_mysql');
        return in_array($driver, $validDrivers, true) ? $driver : 'pdo_mysql';
    }
}
