<?php

declare(strict_types=1);

namespace RickRole\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Junction entity for User-Role relationships.
 *
 * This entity directly maps user IDs to roles, eliminating the need for a separate User entity.
 * User information (name, email, etc.) is managed by the implementing application's own user system.
 */
#[ORM\Entity]
#[ORM\Table(name: 'rick_role_user_roles')]
#[ORM\UniqueConstraint(name: 'user_role_unique', columns: ['user_id', 'role_id'])]
class UserRole
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(name: 'user_id', type: 'string', length: 255)]
    private string $userId;

    #[ORM\ManyToOne(targetEntity: Role::class, inversedBy: 'userRoles')]
    #[ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id', nullable: false)]
    private Role $role;

    #[ORM\Column(name: 'assigned_at', type: 'datetime_immutable')]
    private DateTimeImmutable $assignedAt;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $expiresAt = null;

    public function __construct(string $userId, Role $role, ?DateTimeImmutable $expiresAt = null, ?UuidInterface $id = null)
    {
        $this->id = $id ?? Uuid::uuid7();
        $this->userId = $userId;
        $this->role = $role;
        $this->assignedAt = new DateTimeImmutable();
        $this->expiresAt = $expiresAt;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function getAssignedAt(): DateTimeImmutable
    {
        return $this->assignedAt;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    /**
     * Check if this role assignment is still valid (not expired).
     */
    public function isValid(): bool
    {
        if ($this->expiresAt === null) {
            return true; // No expiration
        }

        return $this->expiresAt > new DateTimeImmutable();
    }

    /**
     * Check if this role assignment has expired.
     */
    public function isExpired(): bool
    {
        return $this->isValid() === false;
    }

    public function isInvalid(): bool
    {
        return $this->isValid() === false;
    }
}
