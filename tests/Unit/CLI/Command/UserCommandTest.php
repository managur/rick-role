<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit\CLI\Command;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RickRole\CLI\Command\UserCommand;
use RickRole\Entity\Role;
use RickRole\Entity\UserRole;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for the UserCommand class.
 */
final class UserCommandTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    /** @var EntityRepository<Role> */
    private EntityRepository $roleRepository;
    /** @var EntityRepository<UserRole> */
    private EntityRepository $userRoleRepository;
    private UserCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->roleRepository = $this->createMock(EntityRepository::class);
        $this->userRoleRepository = $this->createMock(EntityRepository::class);

        // Configure entity manager to return repositories
        $this->entityManager->method('getRepository')
            ->willReturnCallback(function ($class) {
                if ($class === Role::class) {
                    return $this->roleRepository;
                }
                if ($class === UserRole::class) {
                    return $this->userRoleRepository;
                }
                return null;
            });

        // Create command and command tester
        $this->command = new UserCommand($this->entityManager);
        $this->commandTester = new CommandTester($this->command);
    }

    #[Test]
    public function testAssignRoleWithoutUserId(): void
    {
        // Execute command without user ID
        $this->commandTester->execute([
            'action' => 'assign'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('User ID and role name are required', $output);
    }

    #[Test]
    public function testAssignRoleWithoutRoleName(): void
    {
        // Execute command without role name
        $this->commandTester->execute([
            'action' => 'assign',
            '--user-id' => 'user123'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('User ID and role name are required', $output);
    }

    #[Test]
    public function testAssignRoleWithNonExistentRole(): void
    {
        // Configure repository to return null (role doesn't exist)
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'non-existent'])
            ->willReturn(null);

        // Execute command
        $this->commandTester->execute([
            'action' => 'assign',
            '--user-id' => 'user123',
            '--role' => 'non-existent'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role \'non-existent\' not found', $output);
    }

    #[Test]
    public function testAssignRoleWithExistingAssignment(): void
    {
        // Create mock role
        $role = $this->createMock(Role::class);

        // Configure role repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Configure user role repository to return existing assignment
        /** @phpstan-ignore-next-line */
        $this->userRoleRepository->method('findOneBy')
            ->with([
                'userId' => 'user123',
                'role' => $role
            ])
            ->willReturn($this->createMock(UserRole::class));

        // Execute command
        $this->commandTester->execute([
            'action' => 'assign',
            '--user-id' => 'user123',
            '--role' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('User \'user123\' already has role \'admin\' assigned', $output);
    }

    #[Test]
    public function testAssignRoleWithInvalidExpirationDate(): void
    {
        // Create mock role
        $role = $this->createMock(Role::class);

        // Configure role repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Configure user role repository to return null (no existing assignment)
        /** @phpstan-ignore-next-line */
        $this->userRoleRepository->method('findOneBy')
            ->with([
                'userId' => 'user123',
                'role' => $role
            ])
            ->willReturn(null);

        // Execute command with invalid expiration date
        $this->commandTester->execute([
            'action' => 'assign',
            '--user-id' => 'user123',
            '--role' => 'admin',
            '--expires-at' => 'invalid-date'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Invalid expiration date format', $output);
    }

    #[Test]
    public function testAssignRoleSuccessfullyWithoutExpiration(): void
    {
        // Create mock role
        $role = $this->createMock(Role::class);
        $role->method('name')->willReturn('admin');

        // Configure role repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Configure user role repository to return null (no existing assignment)
        /** @phpstan-ignore-next-line */
        $this->userRoleRepository->method('findOneBy')
            ->with([
                'userId' => 'user123',
                'role' => $role
            ])
            ->willReturn(null);

        // Execute command
        $this->commandTester->execute([
            'action' => 'assign',
            '--user-id' => 'user123',
            '--role' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role \'admin\' assigned to user \'user123\'', $output);
        self::assertStringContainsString('expires: Never', $output);
    }

    #[Test]
    public function testAssignRoleSuccessfullyWithExpiration(): void
    {
        // Create mock role
        $role = $this->createMock(Role::class);
        $role->method('name')->willReturn('admin');

        // Configure role repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Configure user role repository to return null (no existing assignment)
        /** @phpstan-ignore-next-line */
        $this->userRoleRepository->method('findOneBy')
            ->with([
                'userId' => 'user123',
                'role' => $role
            ])
            ->willReturn(null);

        // Execute command with expiration date
        $this->commandTester->execute([
            'action' => 'assign',
            '--user-id' => 'user123',
            '--role' => 'admin',
            '--expires-at' => '2025-12-31 23:59:59'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role \'admin\' assigned to user \'user123\'', $output);
        self::assertStringContainsString('expires: 2025-12-31 23:59:59', $output);
    }

    #[Test]
    public function testRemoveRoleWithoutUserId(): void
    {
        // Execute command without user ID
        $this->commandTester->execute([
            'action' => 'remove'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('User ID and role name are required', $output);
    }

    #[Test]
    public function testRemoveRoleWithoutRoleName(): void
    {
        // Execute command without role name
        $this->commandTester->execute([
            'action' => 'remove',
            '--user-id' => 'user123'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('User ID and role name are required', $output);
    }

    #[Test]
    public function testRemoveRoleWithNonExistentRole(): void
    {
        // Configure repository to return null (role doesn't exist)
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'non-existent'])
            ->willReturn(null);

        // Execute command
        $this->commandTester->execute([
            'action' => 'remove',
            '--user-id' => 'user123',
            '--role' => 'non-existent'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role \'non-existent\' not found', $output);
    }

    #[Test]
    public function testRemoveRoleWithNoAssignment(): void
    {
        // Create mock role
        $role = $this->createMock(Role::class);
        $role->method('name')->willReturn('admin');

        // Configure role repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Configure user role repository to return null (no assignment)
        /** @phpstan-ignore-next-line */
        $this->userRoleRepository->method('findOneBy')
            ->with([
                'userId' => 'user123',
                'role' => $role
            ])
            ->willReturn(null);

        // Execute command
        $this->commandTester->execute([
            'action' => 'remove',
            '--user-id' => 'user123',
            '--role' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('User \'user123\' does not have role \'admin\' assigned', $output);
    }

    #[Test]
    public function testRemoveRoleSuccessfully(): void
    {
        // Create mock role
        $role = $this->createMock(Role::class);
        $role->method('name')->willReturn('admin');

        // Create mock user role
        $userRole = $this->createMock(UserRole::class);

        // Configure role repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Configure user role repository to return the assignment
        /** @phpstan-ignore-next-line */
        $this->userRoleRepository->method('findOneBy')
            ->with([
                'userId' => 'user123',
                'role' => $role
            ])
            ->willReturn($userRole);

        // Execute command
        $this->commandTester->execute([
            'action' => 'remove',
            '--user-id' => 'user123',
            '--role' => 'admin'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role \'admin\' removed from user \'user123\'', $output);
    }

    #[Test]
    public function testShowUserRolesWithoutUserId(): void
    {
        // Execute command without user ID
        $this->commandTester->execute([
            'action' => 'roles'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('User ID is required for roles action', $output);
    }

    #[Test]
    public function testShowUserRolesWithNoRoles(): void
    {
        // Configure user role repository to return empty array
        /** @phpstan-ignore-next-line */
        $this->userRoleRepository->method('findBy')
            ->with(['userId' => 'user123'])
            ->willReturn([]);

        // Execute command
        $this->commandTester->execute([
            'action' => 'roles',
            '--user-id' => 'user123'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('User \'user123\' has no roles assigned', $output);
    }

    #[Test]
    public function testShowUserRolesWithRoles(): void
    {
        // Create mock date
        $date = new DateTimeImmutable('2025-01-01 12:00:00');
        $expiryDate = new DateTimeImmutable('2026-01-01 12:00:00');

        // Create mock roles
        $role1 = $this->createConfiguredMock(Role::class, [
            'name' => 'admin',
            'description' => 'Administrator role'
        ]);

        $role2 = $this->createConfiguredMock(Role::class, [
            'name' => 'user',
            'description' => null
        ]);

        // Create mock user roles
        $userRole1 = $this->createConfiguredMock(UserRole::class, [
            'getRole' => $role1,
            'isValid' => true,
            'getAssignedAt' => $date,
            'getExpiresAt' => $expiryDate
        ]);

        $userRole2 = $this->createConfiguredMock(UserRole::class, [
            'getRole' => $role2,
            'isValid' => true,
            'getAssignedAt' => $date,
            'getExpiresAt' => null
        ]);

        // Configure user role repository to return user roles
        /** @phpstan-ignore-next-line */
        $this->userRoleRepository->method('findBy')
            ->with(['userId' => 'user123'])
            ->willReturn([$userRole1, $userRole2]);

        // Execute command
        $this->commandTester->execute([
            'action' => 'roles',
            '--user-id' => 'user123'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Roles assigned to user: user123', $output);
        self::assertStringContainsString('admin', $output);
        self::assertStringContainsString('Administrator role', $output);
        self::assertStringContainsString('user', $output);
        self::assertStringContainsString('No description', $output);
        self::assertStringContainsString('2025-01-01 12:00:00', $output); // Assigned date
        self::assertStringContainsString('2026-01-01 12:00:00', $output); // Expiry date
        self::assertStringContainsString('Never', $output); // No expiry
        self::assertStringContainsString('Active', $output); // Status
    }

    #[Test]
    public function testShowRoleUsersWithoutRoleName(): void
    {
        // Execute command without role name
        $this->commandTester->execute([
            'action' => 'users'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role name is required for users action', $output);
    }

    #[Test]
    public function testShowRoleUsersWithNonExistentRole(): void
    {
        // Configure repository to return null (role doesn't exist)
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'non-existent'])
            ->willReturn(null);

        // Execute command
        $this->commandTester->execute([
            'action' => 'users',
            '--role' => 'non-existent'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Role \'non-existent\' not found', $output);
    }

    #[Test]
    public function testShowRoleUsersWithNoUsers(): void
    {
        // Create mock role with empty user roles collection
        $userRoles = new ArrayCollection([]);
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'empty-role',
            'getUserRoles' => $userRoles
        ]);

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'empty-role'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'users',
            '--role' => 'empty-role'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('No users assigned to role \'empty-role\'', $output);
    }

    #[Test]
    public function testShowRoleUsersWithUsers(): void
    {
        // Create mock date
        $date = new DateTimeImmutable('2025-01-01 12:00:00');
        $expiryDate = new DateTimeImmutable('2026-01-01 12:00:00');

        // Create mock user roles
        $userRole1 = $this->createConfiguredMock(UserRole::class, [
            'userId' => 'user1',
            'isValid' => true,
            'getAssignedAt' => $date,
            'getExpiresAt' => $expiryDate
        ]);

        $userRole2 = $this->createConfiguredMock(UserRole::class, [
            'userId' => 'user2',
            'isValid' => false,
            'getAssignedAt' => $date,
            'getExpiresAt' => null
        ]);

        // Create mock role with user roles
        $userRoles = new ArrayCollection([$userRole1, $userRole2]);
        $role = $this->createConfiguredMock(Role::class, [
            'name' => 'admin',
            'getUserRoles' => $userRoles
        ]);

        // Configure repository to return the role
        /** @phpstan-ignore-next-line */
        $this->roleRepository->method('findOneBy')
            ->with(['name' => 'admin'])
            ->willReturn($role);

        // Execute command
        $this->commandTester->execute([
            'action' => 'users',
            '--role' => 'admin'
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
        self::assertStringContainsString('Expired', $output); // Status
    }

    #[Test]
    public function testUnknownAction(): void
    {
        // Execute command with unknown action
        $this->commandTester->execute([
            'action' => 'invalid-action'
        ]);

        // Verify output
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('Unknown action \'invalid-action\'', $output);
        self::assertStringContainsString('Available actions: assign, remove, roles, users', $output);
    }
}
