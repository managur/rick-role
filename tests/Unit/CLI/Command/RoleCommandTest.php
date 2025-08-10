<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit\CLI\Command;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RickRole\CLI\Command\RoleCommand;
use RickRole\Entity\Role;
use RickRole\Entity\UserRole;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for the RoleCommand class.
 */
final class RoleCommandTest extends TestCase
{
    /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject */
    private EntityManagerInterface $entityManager;
    /** @var EntityRepository<Role>&\PHPUnit\Framework\MockObject\MockObject */
    private EntityRepository $roleRepository;
    private RoleCommand $command;
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
        $this->command = new RoleCommand($this->entityManager);
        $this->commandTester = new CommandTester($this->command);
    }

    #[Test]
    public function testListRolesWithNoRoles(): void
    {
        // Configure repository to return empty array
        $this->roleRepository->method('findAll')->willReturn([]);

        // Execute command
        $this->commandTester->execute(['action' => 'list']);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('No roles found', $output);
    }

    #[Test]
    public function testListRolesWithRoles(): void
    {
        // Create mock roles
        $role1 = $this->createConfiguredMock(Role::class, [
            'name' => 'admin',
            'description' => 'Administrator role',
            'getPermissionNames' => ['create', 'read', 'update', 'delete'],
            'getUserRoles' => new ArrayCollection([
                $this->createMock(UserRole::class),
                $this->createMock(UserRole::class)
            ])
        ]);

        $role2 = $this->createConfiguredMock(Role::class, [
            'name' => 'user',
            'description' => null,
            'getPermissionNames' => ['read'],
            'getUserRoles' => new ArrayCollection([
                $this->createMock(UserRole::class)
            ])
        ]);

        // Configure repository to return roles
        $this->roleRepository->method('findAll')->willReturn([$role1, $role2]);

        // Execute command
        $this->commandTester->execute(['action' => 'list']);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('admin', $output);
        self::assertStringContainsString('Administrator role', $output);
        self::assertStringContainsString('4', $output); // 4 permissions
        self::assertStringContainsString('2', $output); // 2 users

        self::assertStringContainsString('user', $output);
        self::assertStringContainsString('No description', $output);
        self::assertStringContainsString('1', $output); // 1 permission and 1 user
    }

    #[Test]
    public function testCreateRoleWithoutName(): void
    {
        // Execute command without name
        $this->commandTester->execute(['action' => 'create']);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role name is required', $output);
    }

    #[Test]
    public function testCreateRoleWithExistingName(): void
    {
        // Configure repository to return existing role
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($this->createMock(Role::class));

        // Execute command
        $this->commandTester->execute([
            'action' => 'create',
            '--name' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role \'admin\' already exists', $output);
    }

    #[Test]
    public function testCreateRoleSuccessfully(): void
    {
        // Configure repository to return null (role doesn't exist)
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'new-role'])
            ->willReturn(null);

        // Execute command
        $this->commandTester->execute([
            'action' => 'create',
            '--name' => 'new-role',
            '--description' => 'A new role'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role \'new-role\' created successfully', $output);
    }

    #[Test]
    public function testDeleteRoleWithoutName(): void
    {
        // Execute command without name
        $this->commandTester->execute(['action' => 'delete']);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role name is required', $output);
    }

    #[Test]
    public function testDeleteRoleNotFound(): void
    {
        // Configure repository to return null (role doesn't exist)
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'non-existent'])
            ->willReturn(null);

        // Execute command
        $this->commandTester->execute([
            'action' => 'delete',
            '--name' => 'non-existent'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role \'non-existent\' not found', $output);
    }

    #[Test]
    public function testDeleteRoleWithUsersConfirmed(): void
    {
        // Create mock role with users
        $userRoles = new ArrayCollection([
            $this->createMock(UserRole::class),
            $this->createMock(UserRole::class)
        ]);

        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin',
            'getUserRoles' => $userRoles
        ]);

        // Configure repository to return the role
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command with non-interactive mode
        $this->commandTester->execute([
            'action' => 'delete',
            '--name' => 'admin'
        ]);

        // Verify output - in non-interactive mode, confirm() defaults to false, so deletion is cancelled
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role \'admin\' has 2 assigned users', $output);
        self::assertStringContainsString('Role deletion cancelled', $output);
    }

    #[Test]
    public function testDeleteRoleWithUsersCancelled(): void
    {
        // Create mock role with users
        $userRoles = new ArrayCollection([
            $this->createMock(UserRole::class)
        ]);

        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin',
            'getUserRoles' => $userRoles
        ]);

        // Configure repository to return the role
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command with non-interactive mode
        $this->commandTester->execute([
            'action' => 'delete',
            '--name' => 'admin'
        ]);

        // Verify output - in non-interactive mode, confirm() defaults to false, so deletion is cancelled
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role \'admin\' has 1 assigned users', $output);
        self::assertStringContainsString('Role deletion cancelled', $output);
    }

    #[Test]
    public function testDeleteRoleWithoutUsers(): void
    {
        // Create mock role without users
        $userRoles = new ArrayCollection([]);

        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'guest',
            'getUserRoles' => $userRoles
        ]);

        // Configure repository to return the role
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'guest'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'delete',
            '--name' => 'guest'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role \'guest\' deleted successfully', $output);
    }

    #[Test]
    public function testShowRoleWithoutName(): void
    {
        // Execute command without name
        $this->commandTester->execute(['action' => 'show']);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role name is required', $output);
    }

    #[Test]
    public function testShowRoleNotFound(): void
    {
        // Configure repository to return null (role doesn't exist)
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'non-existent'])
            ->willReturn(null);

        // Execute command
        $this->commandTester->execute([
            'action' => 'show',
            '--name' => 'non-existent'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role \'non-existent\' not found', $output);
    }

    #[Test]
    public function testShowRoleWithPermissions(): void
    {
        // Create mock role with permissions
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin',
            'description' => 'Administrator role',
            'getAllPermissions' => [
                'create' => 'ALLOW',
                'delete' => 'DENY'
            ],
            'getPermissions' => [
                'create' => 'ALLOW',
                'delete' => 'DENY'
            ],
            'getExtendedRoles' => new ArrayCollection([]),
            'getExtendedByRoles' => new ArrayCollection([]),
            'getUserRoles' => new ArrayCollection([
                $this->createMock(UserRole::class),
                $this->createMock(UserRole::class)
            ])
        ]);

        // Configure repository to return the role
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'show',
            '--name' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role: admin', $output);
        self::assertStringContainsString('Description: Administrator role', $output);
        self::assertStringContainsString('All permissions (including inherited):', $output);
        self::assertStringContainsString('create', $output);
        self::assertStringContainsString('ALLOW', $output);
        self::assertStringContainsString('delete', $output);
        self::assertStringContainsString('DENY', $output);
        self::assertStringContainsString('Assigned users: 2', $output);
    }

    #[Test]
    public function testShowRoleWithoutPermissions(): void
    {
        // Create mock role without permissions
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'guest',
            'description' => null,
            'getAllPermissions' => [],
            'getPermissions' => [],
            'getExtendedRoles' => new ArrayCollection([]),
            'getExtendedByRoles' => new ArrayCollection([]),
            'getUserRoles' => new ArrayCollection([])
        ]);

        // Configure repository to return the role
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'guest'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'show',
            '--name' => 'guest'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role: guest', $output);
        self::assertStringContainsString('No permissions assigned to this role', $output);
        self::assertStringContainsString('Assigned users: 0', $output);
    }

    #[Test]
    public function testShowRoleUsersWithoutName(): void
    {
        // Execute command without name
        $this->commandTester->execute(['action' => 'users']);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role name is required', $output);
    }

    #[Test]
    public function testShowRoleUsersNotFound(): void
    {
        // Configure repository to return null (role doesn't exist)
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'non-existent'])
            ->willReturn(null);

        // Execute command
        $this->commandTester->execute([
            'action' => 'users',
            '--name' => 'non-existent'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role \'non-existent\' not found', $output);
    }

    #[Test]
    public function testShowRoleUsersWithNoUsers(): void
    {
        // Create mock role without users
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'empty-role',
            'getUserRoles' => new ArrayCollection([])
        ]);

        // Configure repository to return the role
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'empty-role'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'users',
            '--name' => 'empty-role'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('No users assigned to role \'empty-role\'', $output);
    }

    #[Test]
    public function testShowRoleUsersWithUsers(): void
    {
        // Create mock date
        $date = new \DateTimeImmutable('2025-01-01 12:00:00');
        $expiryDate = new \DateTimeImmutable('2026-01-01 12:00:00');

        // Create mock user roles
        $userRole1 = $this->createConfiguredMock(UserRole::class, [
            'userId' => 'user1',
            'isValid' => true,
            'getAssignedAt' => $date,
            'getExpiresAt' => $expiryDate
        ]);

        $userRole2 = $this->createConfiguredMock(UserRole::class, [
            'userId' => 'user2',
            'isValid' => true,
            'getAssignedAt' => $date,
            'getExpiresAt' => null
        ]);

        // Create mock role with users
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin',
            'getUserRoles' => new ArrayCollection([$userRole1, $userRole2])
        ]);

        // Configure repository to return the role
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'users',
            '--name' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Users assigned to role: admin', $output);
        self::assertStringContainsString('user1', $output);
        self::assertStringContainsString('user2', $output);
        self::assertStringContainsString('2025-01-01 12:00:00', $output); // Assigned date
        self::assertStringContainsString('2026-01-01 12:00:00', $output); // Expiry date
        self::assertStringContainsString('Never', $output); // No expiry
        self::assertStringContainsString('Active', $output); // Status
    }

    #[Test]
    public function testUnknownAction(): void
    {
        // Execute command with unknown action
        $this->commandTester->execute(['action' => 'invalid-action']);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Unknown action \'invalid-action\'', $output);
        self::assertStringContainsString('Available actions: list, create, delete, show, users, rename', $output);
    }

    #[Test]
    public function testRenameRoleWithoutName(): void
    {
        // Execute command without role name
        $this->commandTester->execute([
            'action' => 'rename',
            '--new-name' => 'new-role'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role name is required for rename action', $output);
    }

    #[Test]
    public function testRenameRoleWithoutNewName(): void
    {
        // Execute command without new name
        $this->commandTester->execute([
            'action' => 'rename',
            '--name' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('New role name is required for rename action', $output);
    }

    #[Test]
    public function testRenameRoleWithSameName(): void
    {
        // Execute command with same name
        $this->commandTester->execute([
            'action' => 'rename',
            '--name' => 'admin',
            '--new-name' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('New name must be different from current name', $output);
    }

    #[Test]
    public function testRenameRoleNotFound(): void
    {
        // Configure repository to return null (role not found)
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'non-existent'])
            ->willReturn(null);

        // Execute command
        $this->commandTester->execute([
            'action' => 'rename',
            '--name' => 'non-existent',
            '--new-name' => 'new-role'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role \'non-existent\' not found', $output);
    }

    #[Test]
    public function testRenameRoleWithExistingNewName(): void
    {
        // Create mock role to rename
        $roleToRename = $this->createConfiguredMock(Role::class, [
            'name' => 'old-role'
        ]);

        // Create mock role with the new name (already exists)
        $existingRole = $this->createConfiguredMock(Role::class, [
            'name' => 'new-role'
        ]);

        // Configure repository to return different results based on the name parameter
        $this->roleRepository->method('findOneBy')
            ->willReturnCallback(function (array $criteria) use ($roleToRename, $existingRole) {
                if ($criteria['name'] === 'old-role') {
                    return $roleToRename;
                }
                if ($criteria['name'] === 'new-role') {
                    return $existingRole;
                }
                return null;
            });

        // Execute command
        $this->commandTester->execute([
            'action' => 'rename',
            '--name' => 'old-role',
            '--new-name' => 'new-role'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role \'new-role\' already exists', $output);
    }

    #[Test]
    public function testRenameRoleSuccessfully(): void
    {
        // Create mock role
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin'
        ]);
        $role->method('setName')->willReturn($role);

        // Configure repository to return role for old name
        $this->roleRepository->method('findOneBy')
            ->willReturnCallback(function (array $criteria) use ($role) {
                if ($criteria['name'] === 'admin') {
                    return $role;
                }
                if ($criteria['name'] === 'administrator') {
                    return null;
                }
                return null;
            });

        // Configure entity manager to flush
        $this->entityManager->expects(self::once())->method('flush');

        // Execute command
        $this->commandTester->execute([
            'action' => 'rename',
            '--name' => 'admin',
            '--new-name' => 'administrator'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString("Role 'admin' renamed to 'administrator' successfully", $output);
    }

    // Hierarchical Role Tests

    #[Test]
    public function testExtendRoleWithoutName(): void
    {
        // Execute command
        $this->commandTester->execute(['action' => 'extend']);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role name is required for extend action', $output);
    }

    #[Test]
    public function testExtendRoleWithoutExtendsOption(): void
    {
        // Execute command
        $this->commandTester->execute([
            'action' => 'extend',
            '--name' => 'moderator'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role to extend is required for extend action', $output);
    }

    #[Test]
    public function testExtendRoleWithSameName(): void
    {
        // Execute command
        $this->commandTester->execute([
            'action' => 'extend',
            '--name' => 'admin',
            '--extends' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('A role cannot extend itself', $output);
    }

    #[Test]
    public function testExtendRoleNotFound(): void
    {
        // Configure repository to return null for role
        $this->roleRepository->method('findOneBy')
            ->willReturnCallback(function (array $criteria) {
                if ($criteria['name'] === 'moderator') {
                    return null;
                }
                return null;
            });

        // Execute command
        $this->commandTester->execute([
            'action' => 'extend',
            '--name' => 'moderator',
            '--extends' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString("Role 'moderator' not found", $output);
    }

    #[Test]
    public function testExtendRoleWithExtendsRoleNotFound(): void
    {
        // Create mock role
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'moderator'
        ]);

        // Configure repository to return role for moderator, null for admin
        $this->roleRepository->method('findOneBy')
            ->willReturnMap([
                [['name' => 'moderator'], $role],
                [['name' => 'admin'], null]
            ]);

        // Execute command
        $this->commandTester->execute([
            'action' => 'extend',
            '--name' => 'moderator',
            '--extends' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString("Role 'admin' not found", $output);
    }

    #[Test]
    public function testExtendRoleAlreadyExtends(): void
    {
        // Create mock roles
        $moderatorRole = $this->createConfiguredMock(Role::class, [
            'name' => 'moderator',
            'extendsRole' => true
        ]);

        $adminRole = $this->createConfiguredMock(Role::class, [
            'name' => 'admin'
        ]);

        // Configure repository to return roles
        $this->roleRepository->method('findOneBy')
            ->willReturnMap([
                [['name' => 'moderator'], $moderatorRole],
                [['name' => 'admin'], $adminRole]
            ]);

        // Execute command
        $this->commandTester->execute([
            'action' => 'extend',
            '--name' => 'moderator',
            '--extends' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString("Role 'moderator' already extends 'admin'", $output);
    }

    #[Test]
    public function testExtendRoleSuccessfully(): void
    {
        // Create mock roles
        $moderatorRole = $this->createConfiguredMock(Role::class, [
            'name' => 'moderator',
            'extendsRole' => false,
            'extendRole' => $this->createConfiguredMock(Role::class, ['name' => 'moderator'])
        ]);

        $adminRole = $this->createConfiguredMock(Role::class, [
            'name' => 'admin',
            'extendsRole' => false
        ]);

        // Configure repository to return roles
        $this->roleRepository->method('findOneBy')
            ->willReturnMap([
                [['name' => 'moderator'], $moderatorRole],
                [['name' => 'admin'], $adminRole]
            ]);

        // Configure entity manager to flush
        $this->entityManager->expects(self::once())->method('flush');

        // Execute command
        $this->commandTester->execute([
            'action' => 'extend',
            '--name' => 'moderator',
            '--extends' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString("Role 'moderator' now extends 'admin' successfully", $output);
    }

    #[Test]
    public function testUnextendRoleWithoutName(): void
    {
        // Execute command
        $this->commandTester->execute(['action' => 'unextend']);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role name is required for unextend action', $output);
    }

    #[Test]
    public function testUnextendRoleWithoutExtendsOption(): void
    {
        // Execute command
        $this->commandTester->execute([
            'action' => 'unextend',
            '--name' => 'moderator'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role to unextend is required for unextend action', $output);
    }

    #[Test]
    public function testUnextendRoleNotFound(): void
    {
        // Configure repository to return null for role
        $this->roleRepository->method('findOneBy')
            ->willReturnCallback(function (array $criteria) {
                if ($criteria['name'] === 'moderator') {
                    return null;
                }
                return null;
            });

        // Execute command
        $this->commandTester->execute([
            'action' => 'unextend',
            '--name' => 'moderator',
            '--extends' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString("Role 'moderator' not found", $output);
    }

    #[Test]
    public function testUnextendRoleWithExtendsRoleNotFound(): void
    {
        // Create mock role
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'moderator'
        ]);

        // Configure repository to return role for moderator, null for admin
        $this->roleRepository->method('findOneBy')
            ->willReturnMap([
                [['name' => 'moderator'], $role],
                [['name' => 'admin'], null]
            ]);

        // Execute command
        $this->commandTester->execute([
            'action' => 'unextend',
            '--name' => 'moderator',
            '--extends' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString("Role 'admin' not found", $output);
    }

    #[Test]
    public function testUnextendRoleDoesNotExtend(): void
    {
        // Create mock roles
        $moderatorRole = $this->createConfiguredMock(Role::class, [
            'name' => 'moderator',
            'extendsRole' => false
        ]);

        $adminRole = $this->createConfiguredMock(Role::class, [
            'name' => 'admin'
        ]);

        // Configure repository to return roles
        $this->roleRepository->method('findOneBy')
            ->willReturnMap([
                [['name' => 'moderator'], $moderatorRole],
                [['name' => 'admin'], $adminRole]
            ]);

        // Execute command
        $this->commandTester->execute([
            'action' => 'unextend',
            '--name' => 'moderator',
            '--extends' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString("Role 'moderator' does not extend 'admin'", $output);
    }

    #[Test]
    public function testUnextendRoleSuccessfully(): void
    {
        // Create mock roles
        $moderatorRole = $this->createConfiguredMock(Role::class, [
            'name' => 'moderator',
            'extendsRole' => true,
            'removeExtendedRole' => $this->createConfiguredMock(Role::class, ['name' => 'moderator'])
        ]);

        $adminRole = $this->createConfiguredMock(Role::class, [
            'name' => 'admin'
        ]);

        // Configure repository to return roles
        $this->roleRepository->method('findOneBy')
            ->willReturnMap([
                [['name' => 'moderator'], $moderatorRole],
                [['name' => 'admin'], $adminRole]
            ]);

        // Configure entity manager to flush
        $this->entityManager->expects(self::once())->method('flush');

        // Execute command
        $this->commandTester->execute([
            'action' => 'unextend',
            '--name' => 'moderator',
            '--extends' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString("Role 'moderator' no longer extends 'admin'", $output);
    }

    #[Test]
    public function testShowRoleWithExtensions(): void
    {
        // Create mock extended roles
        $adminRole = $this->createConfiguredMock(Role::class, [
            'name' => 'admin'
        ]);

        $supervisorRole = $this->createConfiguredMock(Role::class, [
            'name' => 'supervisor'
        ]);

        $extendedRoles = new ArrayCollection([$adminRole, $supervisorRole]);
        $extendedByRoles = new ArrayCollection([]);

        // Create mock role with extensions
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'moderator',
            'description' => 'Moderator role',
            'getExtendedRoles' => $extendedRoles,
            'getExtendedByRoles' => $extendedByRoles,
            'getAllPermissions' => [
                'read' => 'ALLOW',
                'write' => 'ALLOW',
                'moderate' => 'ALLOW'
            ],
            'getPermissions' => [
                'moderate' => 'ALLOW'
            ],
            'getUserRoles' => new ArrayCollection([])
        ]);

        // Configure repository to return role
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'moderator'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'show',
            '--name' => 'moderator'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role: moderator', $output);
        self::assertStringContainsString('Extends roles:', $output);
        self::assertStringContainsString('admin, supervisor', $output);
        self::assertStringContainsString('Extended by roles: None', $output);
        self::assertStringContainsString('All permissions (including inherited):', $output);
        self::assertStringContainsString('Direct permissions:', $output);
    }

    #[Test]
    public function testListRolesWithExtensions(): void
    {
        // Create mock roles with extensions
        $adminRole = $this->createConfiguredMock(Role::class, [
            'name' => 'admin',
            'description' => 'Administrator role',
            'getPermissionNames' => ['create', 'read', 'update', 'delete'],
            'getUserRoles' => new ArrayCollection([]),
            'getExtendedRoles' => new ArrayCollection([])
        ]);

        $moderatorRole = $this->createConfiguredMock(Role::class, [
            'name' => 'moderator',
            'description' => 'Moderator role',
            'getPermissionNames' => ['read', 'moderate'],
            'getUserRoles' => new ArrayCollection([]),
            'getExtendedRoles' => new ArrayCollection([$adminRole])
        ]);

        // Configure repository to return roles
        $this->roleRepository->method('findAll')->willReturn([$adminRole, $moderatorRole]);

        // Execute command
        $this->commandTester->execute(['action' => 'list']);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('admin', $output);
        self::assertStringContainsString('moderator', $output);
        self::assertStringContainsString('None', $output); // admin has no extensions
        self::assertStringContainsString('admin', $output); // moderator extends admin
    }

    #[Test]
    public function testDeleteRoleWithExtensions(): void
    {
        // Create mock role with extensions
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin',
            'getUserRoles' => new ArrayCollection([]),
            'getExtendedByRoles' => new ArrayCollection([
                $this->createMock(Role::class),
                $this->createMock(Role::class)
            ])
        ]);

        // Configure repository to return role
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Configure entity manager to remove and flush
        $this->entityManager->expects(self::once())->method('remove')->with($role);
        $this->entityManager->expects(self::once())->method('flush');

        // Execute command with confirmation
        $this->commandTester->setInputs(['yes']);
        $this->commandTester->execute([
            'action' => 'delete',
            '--name' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('has 0 assigned users and 2 roles that extend it', $output);
        self::assertStringContainsString("Role 'admin' deleted successfully", $output);
    }

    #[Test]
    public function testExtendRoleWithCircularDependency(): void
    {
        // Create mock roles where admin already extends moderator
        $adminRole = $this->createConfiguredMock(Role::class, [
            'name' => 'admin',
            'extendsRole' => false
        ]);

        $moderatorRole = $this->createConfiguredMock(Role::class, [
            'name' => 'moderator',
            'extendsRole' => true // moderator already extends admin
        ]);

        // Configure repository to return roles
        $this->roleRepository->method('findOneBy')
            ->willReturnMap([
                [['name' => 'admin'], $adminRole],
                [['name' => 'moderator'], $moderatorRole]
            ]);

        // Execute command
        $this->commandTester->execute([
            'action' => 'extend',
            '--name' => 'admin',
            '--extends' => 'moderator'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString("would create circular dependency", $output);
    }

    #[Test]
    public function testShowRoleWithExtendedByRoles(): void
    {
        // Create mock roles
        $adminRole = $this->createConfiguredMock(Role::class, [
            'name' => 'admin'
        ]);

        $moderatorRole = $this->createConfiguredMock(Role::class, [
            'name' => 'moderator'
        ]);

        $extendedRoles = new ArrayCollection([]);
        $extendedByRoles = new ArrayCollection([$adminRole, $moderatorRole]);

        // Create mock role that is extended by other roles
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'base-role',
            'description' => 'Base role',
            'getExtendedRoles' => $extendedRoles,
            'getExtendedByRoles' => $extendedByRoles,
            'getAllPermissions' => [
                'read' => 'ALLOW'
            ],
            'getPermissions' => [
                'read' => 'ALLOW'
            ],
            'getUserRoles' => new ArrayCollection([])
        ]);

        // Configure repository to return role
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'base-role'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'show',
            '--name' => 'base-role'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role: base-role', $output);
        self::assertStringContainsString('Extended by roles:', $output);
        self::assertStringContainsString('admin, moderator', $output);
    }
}
