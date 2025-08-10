<?php

declare(strict_types=1);

namespace RickRole\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Role entity for Rick-Role RBAC system.
 *
 * Roles contain a mapping of permission names to their decision type (ALLOW/DENY).
 * This eliminates the need for a separate Permission entity.
 * Roles can extend other roles to inherit their permissions.
 */
#[ORM\Entity]
#[ORM\Table(name: 'rick_role_roles')]
class Role
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * JSON array mapping permission names to decision types ('ALLOW' or 'DENY')
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: 'json')]
    private array $permissions = [];

    /**
     * @var Collection<int, UserRole>
     */
    #[ORM\OneToMany(mappedBy: 'role', targetEntity: UserRole::class, cascade: ['persist', 'remove'])]
    private Collection $userRoles;

    /**
     * Roles that this role extends (inherits permissions from)
     *
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'extendedBy')]
    #[ORM\JoinTable(name: 'rick_role_role_extensions')]
    #[ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'extended_role_id', referencedColumnName: 'id')]
    private Collection $extends;

    /**
     * Roles that extend this role (this role is inherited by)
     *
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class, mappedBy: 'extends')]
    private Collection $extendedBy;

    public function __construct(string $name, ?string $description = null, ?UuidInterface $id = null)
    {
        $this->id = $id ?? Uuid::uuid7();
        $this->name = $name;
        $this->description = $description;
        $this->permissions = [];
        $this->userRoles = new ArrayCollection();
        $this->extends = new ArrayCollection();
        $this->extendedBy = new ArrayCollection();
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return Collection<int, UserRole>
     */
    public function getUserRoles(): Collection
    {
        return $this->userRoles;
    }

    /**
     * Get all roles that this role extends.
     *
     * @return Collection<int, Role>
     */
    public function getExtendedRoles(): Collection
    {
        return $this->extends;
    }

    /**
     * Get all roles that extend this role.
     *
     * @return Collection<int, Role>
     */
    public function getExtendedByRoles(): Collection
    {
        return $this->extendedBy;
    }

    /**
     * Add a role that this role extends.
     */
    public function extendRole(Role $role): self
    {
        if (!$this->extends->contains($role)) {
            $this->extends->add($role);
        }
        return $this;
    }

    /**
     * Remove a role that this role extends.
     */
    public function removeExtendedRole(Role $role): self
    {
        $this->extends->removeElement($role);
        return $this;
    }

    /**
     * Check if this role extends a specific role.
     */
    public function extendsRole(Role $role): bool
    {
        return $this->extends->contains($role);
    }

    /**
     * Check if this role extends a role by name.
     */
    public function extendsRoleByName(string $roleName): bool
    {
        foreach ($this->extends as $role) {
            if ($role->name() === $roleName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all permission names for this role (including inherited permissions).
     *
     * @return string[]
     */
    public function getPermissionNames(): array
    {
        $permissions = array_keys($this->permissions);

        // Add permissions from extended roles
        foreach ($this->extends as $extendedRole) {
            $permissions = array_merge($permissions, $extendedRole->getPermissionNames());
        }

        return array_unique($permissions);
    }

    /**
     * Check if this role has a specific permission (including inherited permissions).
     */
    public function hasPermission(string $permissionName): bool
    {
        // Check direct permissions first
        if (array_key_exists($permissionName, $this->permissions)) {
            return true;
        }

        // Check inherited permissions
        foreach ($this->extends as $extendedRole) {
            if ($extendedRole->hasPermission($permissionName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the decision type for a specific permission (including inherited permissions).
     *
     * Note: This method returns the first decision found through depth-first traversal
     * of extended roles after checking direct permissions. It is primarily useful for
     * display purposes. The actual authorization decision is made by pooling all
     * decisions (direct and inherited) and resolving conflicts via the configured
     * strategy in the voter layer.
     *
     * @return string|null 'ALLOW', 'DENY', or null if permission doesn't exist
     */
    public function getPermissionDecision(string $permissionName): ?string
    {
        // Check direct permissions first (they take precedence)
        if (array_key_exists($permissionName, $this->permissions)) {
            return $this->permissions[$permissionName];
        }

        // Check inherited permissions
        foreach ($this->extends as $extendedRole) {
            $decision = $extendedRole->getPermissionDecision($permissionName);
            if ($decision !== null) {
                return $decision;
            }
        }

        return null;
    }

    /**
     * Get all permissions as an array (including inherited permissions).
     * All permissions from all extended roles are collected together.
     * Direct permissions are included in the collection but don't override inherited ones.
     *
     * @return array<string, string> Permission name => decision type
     */
    public function getAllPermissions(): array
    {
        $allPermissions = [];

        // Collect all permissions from all extended roles (including nested extensions)
        foreach ($this->extends as $extendedRole) {
            $allPermissions = array_merge($allPermissions, $extendedRole->getAllPermissions());
        }

        // Add direct permissions to the collection (they don't override, just add to the collection)
        $allPermissions = array_merge($allPermissions, $this->permissions);

        return $allPermissions;
    }

    /**
     * Get all permissions with their decisions as a list (not merged by key).
     * This includes all decisions for each permission from all extended roles.
     *
     * @return array<array{permission: string, decision: string, source: string}>
     */
    public function getAllPermissionDecisions(): array
    {
        $allDecisions = [];

        // Collect all permissions from all extended roles (including nested extensions)
        foreach ($this->extends as $extendedRole) {
            $extendedDecisions = $extendedRole->getAllPermissionDecisions();
            $allDecisions = array_merge($allDecisions, $extendedDecisions);
        }

        // Add direct permissions to the collection
        foreach ($this->permissions as $permission => $decision) {
            $allDecisions[] = [
                'permission' => $permission,
                'decision' => $decision,
                'source' => $this->name
            ];
        }

        return $allDecisions;
    }

    /**
     * Add an ALLOW permission to this role.
     */
    public function allowPermission(string $permissionName): self
    {
        $this->permissions[$permissionName] = 'ALLOW';
        return $this;
    }

    /**
     * Add a DENY permission to this role.
     */
    public function denyPermission(string $permissionName): self
    {
        $this->permissions[$permissionName] = 'DENY';
        return $this;
    }

    /**
     * Remove a permission from this role.
     */
    public function removePermission(string $permissionName): self
    {
        unset($this->permissions[$permissionName]);
        return $this;
    }

    /**
     * Get all direct permissions as an array (excluding inherited permissions).
     *
     * @return array<string, string> Permission name => decision type
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * Set all permissions at once.
     *
     * @param array<string, string> $permissions Permission name => decision type
     */
    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;
        return $this;
    }

    /**
     * Clear all permissions from this role.
     */
    public function clearPermissions(): self
    {
        $this->permissions = [];
        return $this;
    }
}
