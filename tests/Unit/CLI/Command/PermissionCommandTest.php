<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit\CLI\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RickRole\CLI\Command\PermissionCommand;
use RickRole\Entity\Role;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for the PermissionCommand class.
 */
final class PermissionCommandTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    /** @var EntityRepository<Role> */
    private EntityRepository $roleRepository;
    private PermissionCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->roleRepository = $this->createMock(EntityRepository::class);

        // Configure entity manager to return role repository
        $this->entityManager->method('getRepository')
            ->with(Role::class)
            ->willReturn($this->roleRepository);

        // Create command and command tester
        $this->command = new PermissionCommand($this->entityManager);
        $this->commandTester = new CommandTester($this->command);
    }

    #[Test]
    public function testRoleNotFound(): void
    {
        // Configure repository to return null (role doesn't exist)
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'non-existent'])
            ->willReturn(null);

        // Execute command
        $this->commandTester->execute([
            'action' => 'list',
            '--role' => 'non-existent'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role \'non-existent\' not found', $output);
    }

    #[Test]
    public function testListPermissionsWithNoPermissions(): void
    {
        // Create mock role without permissions
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'empty-role',
            'getPermissions' => []
        ]);

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'empty-role'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'list',
            '--role' => 'empty-role'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('No permissions assigned to role \'empty-role\'', $output);
    }

    #[Test]
    public function testListPermissionsWithPermissions(): void
    {
        // Create mock role with permissions
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin',
            'getPermissions' => [
                'create' => 'ALLOW',
                'delete' => 'DENY'
            ]
        ]);

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'list',
            '--role' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permissions for role: admin', $output);
        self::assertStringContainsString('create', $output);
        self::assertStringContainsString('ALLOW', $output);
        self::assertStringContainsString('delete', $output);
        self::assertStringContainsString('DENY', $output);
    }

    #[Test]
    public function testAddPermissionWithoutPermissionName(): void
    {
        // Create mock role
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin'
        ]);

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command without permission name
        $this->commandTester->execute([
            'action' => 'add',
            '--role' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission name is required for add action', $output);
    }

    #[Test]
    public function testAddPermissionThatAlreadyExists(): void
    {
        // Create mock role with existing permission
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin',
            'hasPermission' => true
        ]);

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'add',
            '--role' => 'admin',
            '--permission' => 'create'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission \'create\' already exists in role \'admin\'', $output);
    }

    #[Test]
    public function testAddPermissionWithInvalidDecision(): void
    {
        // Create mock role without the permission
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin',
            'hasPermission' => false
        ]);

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command with invalid decision
        $this->commandTester->execute([
            'action' => 'add',
            '--role' => 'admin',
            '--permission' => 'create',
            '--decision' => 'INVALID'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Decision must be either ALLOW or DENY', $output);
    }

    #[Test]
    public function testAddPermissionWithAllowDecision(): void
    {
        // Create mock role without the permission
        $role = $this->createMock(Role::class);
        $role->method('name')->willReturn('admin');
        $role->method('hasPermission')->with('create')->willReturn(false);

        // Expect allowPermission to be called
        $role->expects($this->once())
            ->method('allowPermission')
            ->with('create');

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'add',
            '--role' => 'admin',
            '--permission' => 'create',
            '--decision' => 'ALLOW'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission \'create\' added to role \'admin\' with decision \'ALLOW\'', $output);
    }

    #[Test]
    public function testAddPermissionWithDenyDecision(): void
    {
        // Create mock role without the permission
        $role = $this->createMock(Role::class);
        $role->method('name')->willReturn('admin');
        $role->method('hasPermission')->with('delete')->willReturn(false);

        // Expect denyPermission to be called
        $role->expects($this->once())
            ->method('denyPermission')
            ->with('delete');

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'add',
            '--role' => 'admin',
            '--permission' => 'delete',
            '--decision' => 'DENY'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission \'delete\' added to role \'admin\' with decision \'DENY\'', $output);
    }

    #[Test]
    public function testRemovePermissionWithoutPermissionName(): void
    {
        // Create mock role
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin'
        ]);

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command without permission name
        $this->commandTester->execute([
            'action' => 'remove',
            '--role' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission name is required for remove action', $output);
    }

    #[Test]
    public function testRemovePermissionThatDoesNotExist(): void
    {
        // Create mock role without the permission
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin',
            'hasPermission' => false
        ]);

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'remove',
            '--role' => 'admin',
            '--permission' => 'non-existent'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission \'non-existent\' not found in role \'admin\'', $output);
    }

    #[Test]
    public function testRemovePermissionSuccessfully(): void
    {
        // Create mock role with the permission
        $role = $this->createMock(Role::class);
        $role->method('name')->willReturn('admin');
        $role->method('hasPermission')->with('create')->willReturn(true);

        // Expect removePermission to be called
        $role->expects($this->once())
            ->method('removePermission')
            ->with('create');

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'remove',
            '--role' => 'admin',
            '--permission' => 'create'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission \'create\' removed from role \'admin\'', $output);
    }

    #[Test]
    public function testTogglePermissionWithoutPermissionName(): void
    {
        // Create mock role
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin'
        ]);

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command without permission name
        $this->commandTester->execute([
            'action' => 'toggle',
            '--role' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission name is required for toggle action', $output);
    }

    #[Test]
    public function testTogglePermissionThatDoesNotExist(): void
    {
        // Create mock role without the permission
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin',
            'hasPermission' => false
        ]);

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'toggle',
            '--role' => 'admin',
            '--permission' => 'non-existent'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission \'non-existent\' not found in role \'admin\'', $output);
    }

    #[Test]
    public function testTogglePermissionFromAllowToDeny(): void
    {
        // Create mock role with ALLOW permission
        $role = $this->createMock(Role::class);
        $role->method('name')->willReturn('admin');
        $role->method('hasPermission')->with('create')->willReturn(true);
        $role->method('getPermissionDecision')->with('create')->willReturn('ALLOW');

        // Expect denyPermission to be called
        $role->expects($this->once())
            ->method('denyPermission')
            ->with('create');

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'toggle',
            '--role' => 'admin',
            '--permission' => 'create'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission \'create\' toggled from \'ALLOW\' to \'DENY\' in role \'admin\'', $output);
    }

    #[Test]
    public function testTogglePermissionFromDenyToAllow(): void
    {
        // Create mock role with DENY permission
        $role = $this->createMock(Role::class);
        $role->method('name')->willReturn('admin');
        $role->method('hasPermission')->with('delete')->willReturn(true);
        $role->method('getPermissionDecision')->with('delete')->willReturn('DENY');

        // Expect allowPermission to be called
        $role->expects($this->once())
            ->method('allowPermission')
            ->with('delete');

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'toggle',
            '--role' => 'admin',
            '--permission' => 'delete'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission \'delete\' toggled from \'DENY\' to \'ALLOW\' in role \'admin\'', $output);
    }

    #[Test]
    public function testAllowPermissionWithoutPermissionName(): void
    {
        // Create mock role
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin'
        ]);

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command without permission name
        $this->commandTester->execute([
            'action' => 'allow',
            '--role' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission name is required for allow action', $output);
    }

    #[Test]
    public function testAllowPermissionSuccessfully(): void
    {
        // Create mock role
        $role = $this->createMock(Role::class);
        $role->method('name')->willReturn('admin');

        // Expect allowPermission to be called
        $role->expects($this->once())
            ->method('allowPermission')
            ->with('create');

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'allow',
            '--role' => 'admin',
            '--permission' => 'create'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission \'create\' set to ALLOW in role \'admin\'', $output);
    }

    #[Test]
    public function testDenyPermissionWithoutPermissionName(): void
    {
        // Create mock role
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin'
        ]);

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command without permission name
        $this->commandTester->execute([
            'action' => 'deny',
            '--role' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission name is required for deny action', $output);
    }

    #[Test]
    public function testDenyPermissionSuccessfully(): void
    {
        // Create mock role
        $role = $this->createMock(Role::class);
        $role->method('name')->willReturn('admin');

        // Expect denyPermission to be called
        $role->expects($this->once())
            ->method('denyPermission')
            ->with('delete');

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'deny',
            '--role' => 'admin',
            '--permission' => 'delete'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Permission \'delete\' set to DENY in role \'admin\'', $output);
    }

    #[Test]
    public function testUnknownAction(): void
    {
        // Create mock role
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin'
        ]);

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command with unknown action
        $this->commandTester->execute([
            'action' => 'invalid-action',
            '--role' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Unknown action \'invalid-action\'', $output);
        self::assertStringContainsString('Available actions: list, add, remove, toggle, allow, deny', $output);
    }
}
