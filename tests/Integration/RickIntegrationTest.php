<?php

declare(strict_types=1);

namespace RickRole\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RickRole\Configuration;
use RickRole\Entity\Role;
use RickRole\Entity\UserRole;
use RickRole\Rick;
use RickRole\Strategy\AllowWinsStrategy;
use RickRole\Strategy\DenyWinsStrategy;
use RickRole\Voter\DoctrineDefaultVoter;

/**
 * Integration tests for the Rick-Role system.
 */
final class RickIntegrationTest extends TestCase
{
    #[Test]
    public function testCompleteRickWorkflowWithDenyWinsStrategy(): void
    {
        // Arrange
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRoleRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);

        $role = new Role('admin', 'Admin role');
        $role->allowPermission('read');
        $role->allowPermission('write');
        $role->denyPermission('delete'); // DENY permission

        $userRole = new UserRole('user123', $role);

        $entityManager->method('getRepository')->with(UserRole::class)->willReturn($userRoleRepository);
        $userRoleRepository->method('findBy')->with(['userId' => 'user123'])->willReturn([$userRole]);

        // Test with DenyWinsStrategy (default)
        $config = new Configuration();
        $config->setStrategy(new DenyWinsStrategy());
        $config->addVoter(new DoctrineDefaultVoter($entityManager, new DenyWinsStrategy()));

        $rick = new Rick($config);

        // Act & Assert - ALLOW permissions should work
        $readResult = $rick->allows('user123', 'read');
        $writeResult = $rick->allows('user123', 'write');

        self::assertTrue($readResult, 'Should allow read permission');
        self::assertTrue($writeResult, 'Should allow write permission');

        // Act & Assert - DENY permission should deny access
        $deleteResult = $rick->allows('user123', 'delete');
        self::assertFalse($deleteResult, 'Should deny delete permission due to DENY in role');

        // Act & Assert - Non-existent permission should be denied
        $adminResult = $rick->allows('user123', 'admin');
        self::assertFalse($adminResult, 'Should deny non-existent permission');
    }

    #[Test]
    public function testCompleteRickWorkflowWithAllowWinsStrategy(): void
    {
        // Arrange
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRoleRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);

        $role = new Role('admin', 'Admin role');
        $role->allowPermission('read');
        $role->allowPermission('write');
        $role->denyPermission('delete'); // DENY permission

        $userRole = new UserRole('user123', $role);

        $entityManager->method('getRepository')->with(UserRole::class)->willReturn($userRoleRepository);
        $userRoleRepository->method('findBy')->with(['userId' => 'user123'])->willReturn([$userRole]);

        // Test with AllowWinsStrategy
        $config = new Configuration();
        $config->setStrategy(new AllowWinsStrategy());
        $config->addVoter(new DoctrineDefaultVoter($entityManager, new AllowWinsStrategy()));

        $rick = new Rick($config);

        // Act & Assert - ALLOW permissions should work
        $readResult = $rick->allows('user123', 'read');
        $writeResult = $rick->allows('user123', 'write');

        self::assertTrue($readResult, 'Should allow read permission');
        self::assertTrue($writeResult, 'Should allow write permission');

        // Act & Assert - DENY permission should deny access (even with AllowWinsStrategy)
        $deleteResult = $rick->allows('user123', 'delete');
        self::assertFalse($deleteResult, 'Should deny delete permission due to DENY in role');

        // Act & Assert - Non-existent permission should be denied
        $adminResult = $rick->allows('user123', 'admin');
        self::assertFalse($adminResult, 'Should deny non-existent permission');
    }

    #[Test]
    public function testCannotMethodWorksInIntegration(): void
    {
        // Arrange
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRoleRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);

        $role = new Role('user', 'User role');
        $role->allowPermission('read');
        $role->denyPermission('admin'); // DENY permission

        $userRole = new UserRole('user123', $role);

        $entityManager->method('getRepository')->with(UserRole::class)->willReturn($userRoleRepository);
        $userRoleRepository->method('findBy')->with(['userId' => 'user123'])->willReturn([$userRole]);

        $config = new Configuration();
        $config->addVoter(new DoctrineDefaultVoter($entityManager, new DenyWinsStrategy()));

        $rick = new Rick($config);

        // Act & Assert
        $readResult = $rick->disallows('user123', 'read');
        $adminResult = $rick->disallows('user123', 'admin');
        $writeResult = $rick->disallows('user123', 'write');

        self::assertFalse($readResult, 'Should not disallow read permission');
        self::assertTrue($adminResult, 'Should disallow admin permission due to DENY in role');
        self::assertTrue($writeResult, 'Should disallow non-existent permission');
    }

    #[Test]
    public function testPerformanceWithMultipleVoters(): void
    {
        // Arrange
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRoleRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);

        $role = new Role('admin', 'Admin role');
        $role->allowPermission('read');

        $userRole = new UserRole('user123', $role);

        $entityManager->method('getRepository')->with(UserRole::class)->willReturn($userRoleRepository);
        $userRoleRepository->method('findBy')->with(['userId' => 'user123'])->willReturn([$userRole]);

        $config = new Configuration();
        $config->addVoter(new DoctrineDefaultVoter($entityManager, new DenyWinsStrategy()));
        $config->addVoter(new DoctrineDefaultVoter($entityManager, new DenyWinsStrategy())); // Duplicate for testing

        $rick = new Rick($config);

        // Act
        $startTime = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $rick->allows('user123', 'read');
        }
        $endTime = microtime(true);

        // Assert
        $duration = $endTime - $startTime;
        self::assertLessThan(1.0, $duration, '100 permission checks should complete within 1 second');
    }

    #[Test]
    public function testComplexReasonChainInIntegration(): void
    {
        // Arrange
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRoleRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);

        $role = new Role('user', 'User role');
        $role->denyPermission('admin'); // DENY permission

        $userRole = new UserRole('user123', $role);

        $entityManager->method('getRepository')->with(UserRole::class)->willReturn($userRoleRepository);
        $userRoleRepository->method('findBy')->with(['userId' => 'user123'])->willReturn([$userRole]);

        $config = new Configuration();
        $config->addVoter(new DoctrineDefaultVoter($entityManager, new DenyWinsStrategy()));

        $rick = new Rick($config);

        // Act
        $reason = null;
        $result = $rick->allows('user123', 'admin', null, $reason);

        // Assert
        self::assertFalse($result);
        self::assertStringContainsString('User has DENY permission through role: user', (string) $reason);
    }

    #[Test]
    public function testConfigurationFlexibilityInIntegration(): void
    {
        // Arrange
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRoleRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);

        $role = new Role('flexible_role', 'Flexible Role');
        $role->allowPermission('flexible_permission');

        $userRole = new UserRole('flexible_user', $role);

        $entityManager->method('getRepository')->with(UserRole::class)->willReturn($userRoleRepository);
        $userRoleRepository->method('findBy')->with(['userId' => 'flexible_user'])->willReturn([$userRole]);

        // Test with AllowWinsStrategy (should work when user has permission)
        $config = new Configuration();
        $config->setStrategy(new AllowWinsStrategy());
        $config->addVoter(new DoctrineDefaultVoter($entityManager, new AllowWinsStrategy()));

        $rick = new Rick($config);

        // Act
        $result = $rick->allows('flexible_user', 'flexible_permission');

        // Assert - Should work with AllowWinsStrategy when user has permission
        self::assertTrue($result, 'Should allow access with AllowWinsStrategy');
    }

    #[Test]
    public function testStringAndIntegerUserIdsInIntegration(): void
    {
        // Arrange
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRoleRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);

        $stringRole = new Role('string_role');
        $intRole = new Role('int_role');
        $stringUserRole = new UserRole('string_user', $stringRole);
        $intUserRole = new UserRole('123', $intRole);

        $entityManager->method('getRepository')->with(UserRole::class)->willReturn($userRoleRepository);
        $userRoleRepository->method('findBy')
            ->willReturnMap([
                [['userId' => 'string_user'], [$stringUserRole]],
                [['userId' => '123'], [$intUserRole]],
            ]);

        $config = new Configuration();
        $config->addVoter(new DoctrineDefaultVoter($entityManager, new DenyWinsStrategy()));
        $rick = new Rick($config);

        // Act & Assert - String user ID
        $result1 = $rick->allows('string_user', 'test_permission');
        self::assertFalse($result1); // No permissions assigned

        // Act & Assert - Integer user ID
        $result2 = $rick->allows(123, 'test_permission');
        self::assertFalse($result2); // No permissions assigned
    }

    #[Test]
    public function it_logs_permission_checks_with_monolog_integration(): void
    {
        // Arrange
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRoleRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);

        // Create a user role with permission
        $role = new Role('admin', 'Admin role');
        $role->allowPermission('admin_access');
        $userRole = new UserRole('user123', $role);

        $entityManager->method('getRepository')->with(UserRole::class)->willReturn($userRoleRepository);
        $userRoleRepository->method('findBy')->with(['userId' => 'user123'])->willReturn([$userRole]);

        // Set up Monolog logger with memory handler for testing
        $logger = new \Monolog\Logger('rick-role-test');
        $memoryHandler = new \Monolog\Handler\TestHandler();
        $logger->pushHandler($memoryHandler);

        // Configure Rick with logger
        $config = new Configuration();
        $config->setLogger($logger);
        $config->addVoter(new DoctrineDefaultVoter($entityManager, new DenyWinsStrategy()));

        $rick = new Rick($config);

        // Act
        $result = $rick->allows('user123', 'admin_access');

        // Assert
        self::assertTrue($result);

        // Check that logs were written
        self::assertTrue($memoryHandler->hasInfoRecords());
        self::assertTrue($memoryHandler->hasDebugRecords());

        // Check the final decision log
        $infoRecords = $memoryHandler->getRecords();
        $finalDecisionLog = null;
        foreach ($infoRecords as $record) {
            if ($record['message'] === 'Permission check completed' && $record['level'] === 200) { // INFO level
                $finalDecisionLog = $record;
                break;
            }
        }

        self::assertNotNull($finalDecisionLog);
        /** @var array<string, mixed> $context */
        $context = $finalDecisionLog['context'];
        self::assertSame('user123', $context['user_id']);
        self::assertSame('admin_access', $context['permission']);
        self::assertTrue($context['allowed']);
    }
}
