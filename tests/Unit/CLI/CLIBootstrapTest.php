<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit\CLI;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RickRole\CLI\CLIBootstrap;
use RickRole\CLI\Command\PermissionCommand;
use RickRole\CLI\Command\RoleCommand;
use RickRole\CLI\Command\UserCommand;
use Symfony\Component\Console\Application;

/**
 * Unit tests for the CLIBootstrap class.
 */
final class CLIBootstrapTest extends TestCase
{
    /**
     * @var array<string, mixed> Original environment variables
     */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Backup original environment variables
        $this->originalEnv = [
            'DB_DRIVER' => $_ENV['DB_DRIVER'] ?? '',
            'DB_HOST' => $_ENV['DB_HOST'] ?? '',
            'DB_PORT' => $_ENV['DB_PORT'] ?? '',
            'DB_NAME' => $_ENV['DB_NAME'] ?? '',
            'DB_USER' => $_ENV['DB_USER'] ?? '',
            'DB_PASS' => $_ENV['DB_PASS'] ?? '',
            'APP_ENV' => $_ENV['APP_ENV'] ?? '',
        ];

        // Set test environment variables
        $_ENV['DB_DRIVER'] = 'pdo_sqlite';
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_PORT'] = '3306';
        $_ENV['DB_NAME'] = 'test_db';
        $_ENV['DB_USER'] = 'test_user';
        $_ENV['DB_PASS'] = 'test_pass';
        $_ENV['APP_ENV'] = 'test';
    }

    protected function tearDown(): void
    {
        // Restore original environment variables
        foreach ($this->originalEnv as $key => $value) {
            if ($value === '') {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }

        parent::tearDown();
    }

    #[Test]
    public function testGetEntityManagerReturnsSameInstance(): void
    {
        $entityManager1 = CLIBootstrap::getEntityManager();
        $entityManager2 = CLIBootstrap::getEntityManager();

        self::assertSame($entityManager1, $entityManager2);
        self::assertInstanceOf(EntityManager::class, $entityManager1);
    }

    #[Test]
    public function testCreateApplicationReturnsApplicationWithCommands(): void
    {
        $application = CLIBootstrap::createApplication();

        self::assertInstanceOf(Application::class, $application);
        self::assertEquals('Rick-Role CLI', $application->getName());
        self::assertEquals('1.0.0', $application->getVersion());

        // Check that all commands are registered
        self::assertTrue($application->has('role'));
        self::assertTrue($application->has('user'));
        self::assertTrue($application->has('permission'));

        // Check command instances
        $roleCommand = $application->get('role');
        $userCommand = $application->get('user');
        $permissionCommand = $application->get('permission');

        self::assertInstanceOf(RoleCommand::class, $roleCommand);
        self::assertInstanceOf(UserCommand::class, $userCommand);
        self::assertInstanceOf(PermissionCommand::class, $permissionCommand);
    }

    #[Test]
    public function testGetEnvStringReturnsCorrectValues(): void
    {
        // Test with reflection to access private method
        $reflectionClass = new \ReflectionClass(CLIBootstrap::class);
        $method = $reflectionClass->getMethod('getEnvString');
        $method->setAccessible(true);

        // Test with existing value
        $result1 = $method->invokeArgs(null, ['DB_HOST', 'default_host']);
        self::assertEquals('localhost', $result1);

        // Test with non-existent value
        $result2 = $method->invokeArgs(null, ['NON_EXISTENT_KEY', 'default_value']);
        self::assertEquals('default_value', $result2);

        // Test with non-string value
        $_ENV['TEST_INT_VALUE'] = 123;
        $result3 = $method->invokeArgs(null, ['TEST_INT_VALUE', 'default_value']);
        self::assertEquals('default_value', $result3);
        unset($_ENV['TEST_INT_VALUE']);
    }

    #[Test]
    public function testGetEnvIntReturnsCorrectValues(): void
    {
        // Test with reflection to access private method
        $reflectionClass = new \ReflectionClass(CLIBootstrap::class);
        $method = $reflectionClass->getMethod('getEnvInt');
        $method->setAccessible(true);

        // Test with existing numeric string value
        $_ENV['TEST_PORT'] = '8080';
        $result1 = $method->invokeArgs(null, ['TEST_PORT', 3306]);
        self::assertEquals(8080, $result1);

        // Test with existing integer value
        $_ENV['TEST_PORT_INT'] = 9090;
        $result2 = $method->invokeArgs(null, ['TEST_PORT_INT', 3306]);
        self::assertEquals(9090, $result2);

        // Test with non-existent value
        $result3 = $method->invokeArgs(null, ['NON_EXISTENT_KEY', 5432]);
        self::assertEquals(5432, $result3);

        // Test with non-numeric value
        $_ENV['TEST_NON_NUMERIC'] = 'not-a-number';
        $result4 = $method->invokeArgs(null, ['TEST_NON_NUMERIC', 1234]);
        self::assertEquals(1234, $result4);

        // Clean up
        unset($_ENV['TEST_PORT']);
        unset($_ENV['TEST_PORT_INT']);
        unset($_ENV['TEST_NON_NUMERIC']);
    }

    #[Test]
    public function testGetDatabaseDriverReturnsValidDriver(): void
    {
        // Test with reflection to access private method
        $reflectionClass = new \ReflectionClass(CLIBootstrap::class);
        $method = $reflectionClass->getMethod('getDatabaseDriver');
        $method->setAccessible(true);

        // Test with valid driver
        $_ENV['DB_DRIVER'] = 'pdo_mysql';
        $result1 = $method->invokeArgs(null, []);
        self::assertEquals('pdo_mysql', $result1);

        // Test with invalid driver
        $_ENV['DB_DRIVER'] = 'invalid_driver';
        $result2 = $method->invokeArgs(null, []);
        self::assertEquals('pdo_mysql', $result2); // Should return default

        // Test with empty driver
        unset($_ENV['DB_DRIVER']);
        $result3 = $method->invokeArgs(null, []);
        self::assertEquals('pdo_mysql', $result3); // Should return default
    }
}
