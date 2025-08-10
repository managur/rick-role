<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit\Entity;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RickRole\Entity\Role;
use RickRole\Entity\UserRole;

/**
 * Test class for UserRole entity.
 */
final class UserRoleTest extends TestCase
{
    private string $userId;
    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = 'test-user';
        $this->role = new Role('test-role');
    }

    #[Test]
    public function it_creates_with_user_id_and_role(): void
    {
        $userRole = new UserRole($this->userId, $this->role);

        self::assertSame($this->userId, $userRole->userId());
        self::assertSame($this->role, $userRole->getRole());
    }

    #[Test]
    public function it_handles_different_user_ids(): void
    {
        $otherUserId = 'user456';
        $userRole = new UserRole($otherUserId, $this->role);

        self::assertSame($otherUserId, $userRole->userId());
    }

    #[Test]
    public function it_handles_different_roles(): void
    {
        $userRole = new Role('user');
        $userRoleEntity = new UserRole($this->userId, $userRole);

        self::assertSame($userRole, $userRoleEntity->getRole());
    }

    #[Test]
    public function it_gets_id(): void
    {
        $userRole = new UserRole($this->userId, $this->role);

        $id = $userRole->getId();
        self::assertNotEmpty($id->toString());
    }

    #[Test]
    public function it_gets_assigned_at(): void
    {
        $userRole = new UserRole($this->userId, $this->role);

        $assignedAt = $userRole->getAssignedAt();
        self::assertInstanceOf(DateTimeImmutable::class, $assignedAt);
        self::assertLessThanOrEqual(new DateTimeImmutable(), $assignedAt);
    }

    #[Test]
    public function it_gets_expires_at_when_not_set(): void
    {
        $userRole = new UserRole($this->userId, $this->role);

        self::assertNull($userRole->getExpiresAt());
    }

    #[Test]
    public function it_gets_expires_at_when_set(): void
    {
        $expiresAt = new DateTimeImmutable('+1 day');
        $userRole = new UserRole($this->userId, $this->role, $expiresAt);

        self::assertSame($expiresAt, $userRole->getExpiresAt());
    }

    #[Test]
    public function it_sets_expires_at(): void
    {
        $userRole = new UserRole($this->userId, $this->role);
        $newExpiresAt = new DateTimeImmutable('+1 week');

        $result = $userRole->setExpiresAt($newExpiresAt);

        self::assertSame($userRole, $result);
        self::assertSame($newExpiresAt, $userRole->getExpiresAt());
    }

    #[Test]
    public function it_sets_expires_at_to_null(): void
    {
        $expiresAt = new DateTimeImmutable('+1 day');
        $userRole = new UserRole($this->userId, $this->role, $expiresAt);

        $result = $userRole->setExpiresAt(null);

        self::assertSame($userRole, $result);
        self::assertNull($userRole->getExpiresAt());
    }

    #[Test]
    public function it_is_valid_when_no_expiration(): void
    {
        $userRole = new UserRole($this->userId, $this->role);

        self::assertTrue($userRole->isValid());
    }

    #[Test]
    public function it_is_valid_when_expiration_in_future(): void
    {
        $expiresAt = new DateTimeImmutable('+1 day');
        $userRole = new UserRole($this->userId, $this->role, $expiresAt);

        self::assertTrue($userRole->isValid());
    }

    #[Test]
    public function it_is_invalid_when_expired(): void
    {
        $expiresAt = new DateTimeImmutable('-1 day');
        $userRole = new UserRole($this->userId, $this->role, $expiresAt);

        self::assertFalse($userRole->isValid());
    }

    #[Test]
    public function it_is_expired_when_expired(): void
    {
        $expiresAt = new DateTimeImmutable('-1 day');
        $userRole = new UserRole($this->userId, $this->role, $expiresAt);

        self::assertTrue($userRole->isExpired());
    }

    #[Test]
    public function it_is_not_expired_when_valid(): void
    {
        $expiresAt = new DateTimeImmutable('+1 day');
        $userRole = new UserRole($this->userId, $this->role, $expiresAt);

        self::assertFalse($userRole->isExpired());
    }

    #[Test]
    public function it_is_invalid_when_expired_using_is_invalid(): void
    {
        $expiresAt = new DateTimeImmutable('-1 day');
        $userRole = new UserRole($this->userId, $this->role, $expiresAt);

        self::assertTrue($userRole->isInvalid());
    }

    #[Test]
    public function it_is_not_invalid_when_valid(): void
    {
        $expiresAt = new DateTimeImmutable('+1 day');
        $userRole = new UserRole($this->userId, $this->role, $expiresAt);

        self::assertFalse($userRole->isInvalid());
    }

    #[Test]
    public function it_handles_edge_case_expiration_at_current_time(): void
    {
        $expiresAt = new DateTimeImmutable();
        $userRole = new UserRole($this->userId, $this->role, $expiresAt);

        // Should be invalid since expiration is at or before current time
        self::assertFalse($userRole->isValid());
        self::assertTrue($userRole->isExpired());
        self::assertTrue($userRole->isInvalid());
    }
}
