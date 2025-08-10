<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit\Voter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository; // @template T
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RickRole\Entity\Role;
use RickRole\Entity\UserRole;
use RickRole\Strategy\DenyWinsStrategy;
use RickRole\Voter\DoctrineDefaultVoter;
use RickRole\Voter\VoteResult;

/**
 * Unit tests for the DoctrineDefaultVoter class.
 */
final class DoctrineDefaultVoterTest extends TestCase
{
    /** @var EntityRepository<UserRole> */
    private EntityRepository $userRoleRepository;
    private DoctrineDefaultVoter $voter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRoleRepository = $this->createMock(EntityRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(UserRole::class)->willReturn($this->userRoleRepository);
        $strategy = new DenyWinsStrategy();
        $this->voter = new DoctrineDefaultVoter($entityManager, $strategy);
    }

    #[Test]
    public function it_creates_with_entity_manager_and_strategy(): void
    {
        self::assertInstanceOf(DoctrineDefaultVoter::class, $this->voter);
    }

    #[Test]
    public function it_throws_exception_without_entity_manager(): void
    {
        $this->expectException(\TypeError::class);
        $strategy = new DenyWinsStrategy();
        // @phpstan-ignore-next-line
        new DoctrineDefaultVoter(null, $strategy);
    }

    #[Test]
    public function it_throws_exception_without_strategy(): void
    {
        $this->expectException(\TypeError::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        // @phpstan-ignore-next-line
        new DoctrineDefaultVoter($entityManager, null);
    }

    #[Test]
    public function it_denies_when_user_not_found(): void
    {
        /** @phpstan-ignore-next-line */
        $this->userRoleRepository
            ->method('findBy')
            ->with(['userId' => '123'])
            ->willReturn([]);

        $result = $this->voter->vote(123, 'read');

        self::assertTrue($result->isDeny());
        self::assertStringContainsString('User not found or has no roles', $result->message());
    }

    #[Test]
    public function it_allows_when_user_has_allow_permission(): void
    {
        $role = new Role('user');
        $role->allowPermission('read');
        $userRole = new UserRole('123', $role);

        /** @phpstan-ignore-next-line */
        $this->userRoleRepository
            ->method('findBy')
            ->with(['userId' => '123'])
            ->willReturn([$userRole]);

        $result = $this->voter->vote('123', 'read');

        self::assertTrue($result->isAllow());
        self::assertStringContainsString('User has ALLOW permission through role: user', $result->message());
    }

    #[Test]
    public function it_denies_when_user_has_deny_permission(): void
    {
        $role = new Role('user');
        $role->denyPermission('admin');
        $userRole = new UserRole('123', $role);

        /** @phpstan-ignore-next-line */
        $this->userRoleRepository
            ->method('findBy')
            ->with(['userId' => '123'])
            ->willReturn([$userRole]);

        $result = $this->voter->vote('123', 'admin');

        self::assertTrue($result->isDeny());
        self::assertStringContainsString('User has DENY permission through role: user', $result->message());
    }

    #[Test]
    public function it_denies_when_user_lacks_permission(): void
    {
        $role = new Role('user');
        // No permissions added to role
        $userRole = new UserRole('123', $role);

        /** @phpstan-ignore-next-line */
        $this->userRoleRepository
            ->method('findBy')
            ->with(['userId' => '123'])
            ->willReturn([$userRole]);

        $result = $this->voter->vote('123', 'read');

        self::assertTrue($result->isDeny());
        self::assertStringContainsString('User does not have permission: read', $result->message());
    }

    #[Test]
    public function it_handles_multiple_roles(): void
    {
        $userRole = new Role('user');
        $userRole->allowPermission('read');

        $editorRole = new Role('editor');
        $editorRole->allowPermission('write');

        $userRole1 = new UserRole('123', $userRole);
        $userRole2 = new UserRole('123', $editorRole);

        /** @phpstan-ignore-next-line */
        $this->userRoleRepository
            ->method('findBy')
            ->with(['userId' => '123'])
            ->willReturn([$userRole1, $userRole2]);

        $readResult = $this->voter->vote('123', 'read');
        $writeResult = $this->voter->vote('123', 'write');
        $adminResult = $this->voter->vote('123', 'admin');

        self::assertTrue($readResult->isAllow());
        self::assertTrue($writeResult->isAllow());
        self::assertTrue($adminResult->isDeny());
    }

    #[Test]
    public function it_handles_empty_permission_string(): void
    {
        $role = new Role('user');
        $userRole = new UserRole('123', $role);

        /** @phpstan-ignore-next-line */
        $this->userRoleRepository
            ->method('findBy')
            ->with(['userId' => '123'])
            ->willReturn([$userRole]);

        $result = $this->voter->vote('123', '');

        self::assertTrue($result->isDeny());
    }

    #[Test]
    public function it_handles_subject_parameter(): void
    {
        $role = new Role('user');
        $role->allowPermission('read');
        $userRole = new UserRole('123', $role);

        /** @phpstan-ignore-next-line */
        $this->userRoleRepository
            ->method('findBy')
            ->with(['userId' => '123'])
            ->willReturn([$userRole]);

        $subject = new \stdClass();
        $result = $this->voter->vote('123', 'read', $subject);

        self::assertTrue($result->isAllow());
    }

    #[Test]
    public function it_handles_entity_manager_exception(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willThrowException(new \Exception('Database error'));
        $strategy = new DenyWinsStrategy();
        $voter = new DoctrineDefaultVoter($entityManager, $strategy);

        $result = $voter->vote('123', 'read');

        self::assertTrue($result->isDeny());
        self::assertStringContainsString('Error checking user permissions', $result->message());
    }

    #[Test]
    public function it_denies_when_user_has_roles_but_no_matching_permissions(): void
    {
        // Create a role with permissions that don't match the requested permission
        $role = new Role('user');
        $role->allowPermission('write');  // User has 'write' permission
        $role->denyPermission('delete');  // User has 'delete' denied
        $userRole = new UserRole('123', $role);

        /** @phpstan-ignore-next-line */
        $this->userRoleRepository
            ->method('findBy')
            ->with(['userId' => '123'])
            ->willReturn([$userRole]);

        // Request a permission that the user doesn't have
        $result = $this->voter->vote('123', 'read');

        self::assertTrue($result->isDeny());
        self::assertStringContainsString('User does not have permission: read', $result->message());
    }



    #[Test]
    public function it_handles_complex_permission_structures(): void
    {
        $role = new Role('admin');
        $role->allowPermission('read');
        $role->allowPermission('write');
        $role->denyPermission('delete');

        $userRole = new UserRole('admin-user', $role);

        /** @phpstan-ignore-next-line */
        $this->userRoleRepository
            ->method('findBy')
            ->with(['userId' => 'admin-user'])
            ->willReturn([$userRole]);

        $permissions = ['read', 'write', 'delete', 'admin'];
        $results = [];

        foreach ($permissions as $permission) {
            $results[$permission] = $this->voter->vote('admin-user', $permission);
        }

        self::assertTrue($results['read']->isAllow());
        self::assertTrue($results['write']->isAllow());
        self::assertTrue($results['delete']->isDeny());
        self::assertTrue($results['admin']->isDeny());
    }

    #[Test]
    public function it_returns_vote_result_instance(): void
    {
        $role = new Role('user');
        $userRole = new UserRole('123', $role);
        /** @phpstan-ignore-next-line */
        $this->userRoleRepository
            ->method('findBy')
            ->with(['userId' => '123'])
            ->willReturn([$userRole]);

        $result = $this->voter->vote('123', 'read');

        self::assertInstanceOf(VoteResult::class, $result);
    }

    #[Test]
    public function it_handles_permission_case_sensitivity(): void
    {
        $role = new Role('user');
        $role->allowPermission('READ'); // Uppercase
        $userRole = new UserRole('123', $role);

        /** @phpstan-ignore-next-line */
        $this->userRoleRepository
            ->method('findBy')
            ->with(['userId' => '123'])
            ->willReturn([$userRole]);

        $upperResult = $this->voter->vote('123', 'READ');
        $lowerResult = $this->voter->vote('123', 'read');

        self::assertTrue($upperResult->isAllow());
        self::assertTrue($lowerResult->isDeny()); // Case sensitive
    }

    #[Test]
    public function it_handles_role_without_permissions(): void
    {
        $role = new Role('empty-role');
        // No permissions added to role

        $userRole = new UserRole('123', $role);

        /** @phpstan-ignore-next-line */
        $this->userRoleRepository
            ->method('findBy')
            ->with(['userId' => '123'])
            ->willReturn([$userRole]);

        $result = $this->voter->vote('123', 'any-permission');

        self::assertTrue($result->isDeny());
        self::assertStringContainsString('User does not have permission: any-permission', $result->message());
    }

    #[DataProvider('userIdTypesData')]
    #[Test]
    public function it_votes_with_different_user_id_types(int|string $userId, string $permission, bool $shouldFindUser): void
    {
        if ($shouldFindUser) {
            $role = new Role('user');
            $role->allowPermission($permission);
            $userRole = new UserRole((string)$userId, $role);

            /** @phpstan-ignore-next-line */
            $this->userRoleRepository
                ->method('findBy')
                ->with(['userId' => (string)$userId])
                ->willReturn([$userRole]);
        } else {
            /** @phpstan-ignore-next-line */
            $this->userRoleRepository
                ->method('findBy')
                ->with(['userId' => (string)$userId])
                ->willReturn([]);
        }

        $result = $this->voter->vote($userId, $permission);

        if ($shouldFindUser) {
            self::assertTrue($result->isAllow());
        } else {
            self::assertTrue($result->isDeny());
        }
    }

    /** @return array<string, array{0: int|string, 1: string, 2: bool}> */
    public static function userIdTypesData(): array
    {
        return [
            'string user ID' => ['user123', 'read', true],
            'integer user ID' => [123, 'write', true],
            'zero user ID' => [0, 'admin', true],
            'empty string ID' => ['', 'read', false],
        ];
    }

    #[Test]
    public function it_accepts_stringable_permission(): void
    {
        $role = new Role('user');
        $userRole = new UserRole('123', $role);
        /** @phpstan-ignore-next-line */
        $this->userRoleRepository
            ->method('findBy')
            ->with(['userId' => '123'])
            ->willReturn([$userRole]);

        $stringablePermission = new class {
            public function __toString(): string
            {
                return 'read';
            }
        };

        $result = $this->voter->vote('123', $stringablePermission);

        self::assertTrue($result->isDeny()); // User has no permissions
    }

    #[Test]
    public function it_skips_expired_roles_and_denies_if_no_valid_roles(): void
    {
        $role = new Role('user');
        $role->allowPermission('read');

        $expiredUserRole = $this->createMock(UserRole::class);
        $expiredUserRole->method('isValid')->willReturn(false);
        $expiredUserRole->method('getRole')->willReturn($role);

        /** @phpstan-ignore-next-line */
        $this->userRoleRepository
            ->method('findBy')
            ->with(['userId' => 'expired-user'])
            ->willReturn([$expiredUserRole]);

        $result = $this->voter->vote('expired-user', 'read');

        self::assertTrue($result->isDeny());
        self::assertStringContainsString('User does not have permission: read', $result->message());
    }

    #[Test]
    public function it_abstains_when_permission_decision_is_unknown(): void
    {
        $role = new \RickRole\Entity\Role('user');
        // Directly set a permission to an unknown value
        $role->setPermissions(['read' => 'MAYBE']);
        $userRole = new \RickRole\Entity\UserRole('123', $role);

        /** @phpstan-ignore-next-line */
        $this->userRoleRepository
            ->method('findBy')
            ->with(['userId' => '123'])
            ->willReturn([$userRole]);

        $result = $this->voter->vote('123', 'read');

        self::assertTrue($result->isAbstain());
        self::assertStringContainsString('All voters abstained', $result->message());
    }

    #[Test]
    public function hierarchical_roles_collect_all_permissions_and_apply_strategy(): void
    {
        // Create roles with hierarchical structure
        $roleA = new Role('role-a');
        $roleB = new Role('role-b');
        $roleC = new Role('role-c');

        // Role B allows permission X
        $roleB->allowPermission('permission-x');

        // Role C denies permission X
        $roleC->denyPermission('permission-x');

        // Role A extends both B and C
        $roleA->extendRole($roleB);
        $roleA->extendRole($roleC);

        // Create user role assignments
        $userRoleA = new UserRole('user123', $roleA);

        // Mock the repository to return our user roles
        /** @phpstan-ignore-next-line */
        $this->userRoleRepository
            ->method('findBy')
            ->with(['userId' => 'user123'])
            ->willReturn([$userRoleA]);

        $result = $this->voter->vote('user123', 'permission-x');

        // Should deny because DenyWinsStrategy and we have 1 ALLOW and 1 DENY
        self::assertTrue($result->isDeny());
        self::assertFalse($result->isAllow());
    }

    #[Test]
    public function hierarchical_roles_with_allow_wins_strategy(): void
    {
        // Create roles with hierarchical structure
        $roleA = new Role('role-a');
        $roleB = new Role('role-b');
        $roleC = new Role('role-c');

        // Role B allows permission X
        $roleB->allowPermission('permission-x');

        // Role C denies permission X
        $roleC->denyPermission('permission-x');

        // Role A extends both B and C
        $roleA->extendRole($roleB);
        $roleA->extendRole($roleC);

        // Create user role assignments
        $userRoleA = new UserRole('user123', $roleA);

        // Mock the repository to return our user roles
        /** @phpstan-ignore-next-line */
        $this->userRoleRepository
            ->method('findBy')
            ->with(['userId' => 'user123'])
            ->willReturn([$userRoleA]);

        // Create a new voter with AllowWinsStrategy
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(UserRole::class)->willReturn($this->userRoleRepository);
        $strategy = new \RickRole\Strategy\AllowWinsStrategy();
        $voter = new DoctrineDefaultVoter($entityManager, $strategy);

        $result = $voter->vote('user123', 'permission-x');

        // Should allow because AllowWinsStrategy and we have 1 ALLOW and 1 DENY
        self::assertTrue($result->isAllow());
        self::assertFalse($result->isDeny());
    }
}
