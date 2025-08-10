<?php

declare(strict_types=1);

namespace RickRole\CLI\Command;

use Doctrine\ORM\EntityManagerInterface;
use RickRole\Entity\Role;
use RickRole\Entity\UserRole;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Role management command for Rick-Role CLI.
 */
#[AsCommand(
    name: 'role',
    description: 'Manage roles in the Rick-Role system'
)]
final class RoleCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform (list, create, delete, show, users, rename, extend, unextend)')
            ->addOption('name', 'r', InputOption::VALUE_REQUIRED, 'Role name')
            ->addOption('description', 'd', InputOption::VALUE_REQUIRED, 'Role description for create action')
            ->addOption('new-name', 'w', InputOption::VALUE_REQUIRED, 'New role name for rename action')
            ->addOption('extends', 'e', InputOption::VALUE_REQUIRED, 'Role name to extend (for extend action)')
            ->setHelp(<<<'HELP'
The <info>role</info> command manages roles in the Rick-Role system.

Available actions:
  <info>list</info>                    - List all roles
  <info>create</info>                  - Create a new role (requires -r)
  <info>delete</info>                  - Delete a role (requires -r)
  <info>show</info>                    - Show role details and permissions (requires -r)
  <info>users</info>                   - Show users assigned to a role (requires -r)
  <info>rename</info>                  - Rename a role (requires -r and -w)
  <info>extend</info>                  - Make a role extend another role (requires -r and -e)
  <info>unextend</info>                - Remove role extension (requires -r and -e)

Examples:
  <info>rick role list</info>
  <info>rick role create -r admin -d "Administrator role"</info>
  <info>rick role show -r admin</info>
  <info>rick role users -r admin</info>
  <info>rick role delete -r admin</info>
  <info>rick role rename -r admin -w administrator</info>
  <info>rick role extend -r probationary-admin -e admin</info>
  <info>rick role unextend -r probationary-admin -e admin</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $this->getStringArgument($input, 'action');
        $name = $input->getOption('name');
        $name = is_string($name) ? $name : null;

        return match ($action) {
            'list' => $this->listRoles($io),
            'create' => $this->createRole($io, $name, $input),
            'delete' => $this->deleteRole($io, $name),
            'show' => $this->showRole($io, $name),
            'users' => $this->showRoleUsers($io, $name),
            'rename' => $this->renameRole($io, $name, $input),
            'extend' => $this->extendRole($io, $name, $input),
            'unextend' => $this->unextendRole($io, $name, $input),
            default => $this->showHelp($io, $action),
        };
    }

    /**
     * Get a string argument from input with proper type handling.
     */
    private function getStringArgument(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);
        return is_string($value) ? $value : '';
    }

    private function listRoles(SymfonyStyle $io): int
    {
        $roleRepository = $this->entityManager->getRepository(Role::class);
        $roles = $roleRepository->findAll();

        if (empty($roles)) {
            $io->info('No roles found.');
            return Command::SUCCESS;
        }

        $tableData = [];
        foreach ($roles as $role) {
            $permissionCount = count($role->getPermissionNames());
            $userCount = $role->getUserRoles()->count();
            $extendedRoles = $role->getExtendedRoles();
            $extendedRoleNames = [];
            foreach ($extendedRoles as $extendedRole) {
                $extendedRoleNames[] = $extendedRole->name();
            }
            $extensionsText = empty($extendedRoleNames) ? 'None' : implode(', ', $extendedRoleNames);

            $tableData[] = [
                $role->name(),
                $role->description() ?? 'No description',
                $permissionCount,
                $userCount,
                $extensionsText,
            ];
        }

        $io->table(
            ['Name', 'Description', 'Permissions', 'Users', 'Extends'],
            $tableData
        );

        return Command::SUCCESS;
    }

    private function createRole(SymfonyStyle $io, ?string $name, InputInterface $input): int
    {
        if ($name === null) {
            $io->error('Role name is required for create action.');
            return Command::INVALID;
        }

        $roleRepository = $this->entityManager->getRepository(Role::class);
        $existingRole = $roleRepository->findOneBy(['name' => $name]);

        if ($existingRole !== null) {
            $io->error("Role '{$name}' already exists.");
            return Command::FAILURE;
        }

        $description = $input->getOption('description');
        $description = is_string($description) ? $description : null;

        $role = new Role($name, $description);
        $this->entityManager->persist($role);
        $this->entityManager->flush();

        $io->success("Role '{$name}' created successfully.");
        return Command::SUCCESS;
    }

    private function deleteRole(SymfonyStyle $io, ?string $name): int
    {
        if ($name === null) {
            $io->error('Role name is required for delete action.');
            return Command::INVALID;
        }

        $roleRepository = $this->entityManager->getRepository(Role::class);
        $role = $roleRepository->findOneBy(['name' => $name]);

        if ($role === null) {
            $io->error("Role '{$name}' not found.");
            return Command::FAILURE;
        }

        $userCount = $role->getUserRoles()->count();
        $extendedByCount = $role->getExtendedByRoles()->count();

        if ($userCount > 0 || $extendedByCount > 0) {
            $message = "Role '{$name}' has {$userCount} assigned users and {$extendedByCount} roles that extend it. Are you sure you want to delete it?";
            $confirmed = $io->confirm($message, false);

            if ($confirmed === false) {
                $io->info('Role deletion cancelled.');
                return Command::SUCCESS;
            }
        }

        $this->entityManager->remove($role);
        $this->entityManager->flush();

        $io->success("Role '{$name}' deleted successfully.");
        return Command::SUCCESS;
    }

    private function showRole(SymfonyStyle $io, ?string $name): int
    {
        if ($name === null) {
            $io->error('Role name is required for show action.');
            return Command::INVALID;
        }

        $roleRepository = $this->entityManager->getRepository(Role::class);
        $role = $roleRepository->findOneBy(['name' => $name]);

        if ($role === null) {
            $io->error("Role '{$name}' not found.");
            return Command::FAILURE;
        }

        $io->section("Role: {$role->name()}");

        if ($role->description() !== null) {
            $io->text("Description: {$role->description()}");
        }

        // Show extended roles
        $extendedRoles = $role->getExtendedRoles();
        if (!$extendedRoles->isEmpty()) {
            $io->text('Extends roles:');
            $extendedRoleNames = [];
            foreach ($extendedRoles as $extendedRole) {
                $extendedRoleNames[] = $extendedRole->name();
            }
            $io->text('  ' . implode(', ', $extendedRoleNames));
        } else {
            $io->text('Extends roles: None');
        }

        // Show roles that extend this role
        $extendedByRoles = $role->getExtendedByRoles();
        if (!$extendedByRoles->isEmpty()) {
            $io->text('Extended by roles:');
            $extendedByRoleNames = [];
            foreach ($extendedByRoles as $extendedByRole) {
                $extendedByRoleNames[] = $extendedByRole->name();
            }
            $io->text('  ' . implode(', ', $extendedByRoleNames));
        } else {
            $io->text('Extended by roles: None');
        }

        // Show all permissions (including inherited)
        $allPermissions = $role->getAllPermissions();
        if (empty($allPermissions)) {
            $io->info('No permissions assigned to this role.');
        } else {
            $io->text('All permissions (including inherited):');
            $permissionData = [];
            foreach ($allPermissions as $permission => $decision) {
                $permissionData[] = [$permission, $decision];
            }
            $io->table(['Permission', 'Decision'], $permissionData);
        }

        // Show direct permissions only
        $directPermissions = $role->getPermissions();
        if (!empty($directPermissions)) {
            $io->text('Direct permissions:');
            $directPermissionData = [];
            foreach ($directPermissions as $permission => $decision) {
                $directPermissionData[] = [$permission, $decision];
            }
            $io->table(['Permission', 'Decision'], $directPermissionData);
        }

        $userCount = $role->getUserRoles()->count();
        $io->text("Assigned users: {$userCount}");

        return Command::SUCCESS;
    }

    private function showRoleUsers(SymfonyStyle $io, ?string $name): int
    {
        if ($name === null) {
            $io->error('Role name is required for users action.');
            return Command::INVALID;
        }

        $roleRepository = $this->entityManager->getRepository(Role::class);
        $role = $roleRepository->findOneBy(['name' => $name]);

        if ($role === null) {
            $io->error("Role '{$name}' not found.");
            return Command::FAILURE;
        }

        $userRoles = $role->getUserRoles();

        if ($userRoles->isEmpty()) {
            $io->info("No users assigned to role '{$name}'.");
            return Command::SUCCESS;
        }

        $io->section("Users assigned to role: {$role->name()}");

        $tableData = [];
        foreach ($userRoles as $userRole) {
            $status = $userRole->isValid() ? 'Active' : 'Expired';
            $expiresAt = $userRole->getExpiresAt();
            $expiryText = $expiresAt !== null ? $expiresAt->format('Y-m-d H:i:s') : 'Never';

            $tableData[] = [
                $userRole->userId(),
                $userRole->getAssignedAt()->format('Y-m-d H:i:s'),
                $expiryText,
                $status,
            ];
        }

        $io->table(
            ['User ID', 'Assigned At', 'Expires At', 'Status'],
            $tableData
        );

        return Command::SUCCESS;
    }

    private function renameRole(SymfonyStyle $io, ?string $name, InputInterface $input): int
    {
        if ($name === null) {
            $io->error('Role name is required for rename action.');
            return Command::INVALID;
        }

        $newName = $input->getOption('new-name');
        $newName = is_string($newName) ? $newName : null;

        if ($newName === null) {
            $io->error('New role name is required for rename action.');
            return Command::INVALID;
        }

        if ($name === $newName) {
            $io->error('New name must be different from current name.');
            return Command::INVALID;
        }

        $roleRepository = $this->entityManager->getRepository(Role::class);
        $role = $roleRepository->findOneBy(['name' => $name]);

        if ($role === null) {
            $io->error("Role '{$name}' not found.");
            return Command::FAILURE;
        }

        $existingRoleWithNewName = $roleRepository->findOneBy(['name' => $newName]);
        if ($existingRoleWithNewName !== null) {
            $io->error("Role '{$newName}' already exists.");
            return Command::FAILURE;
        }

        $oldName = $role->name();
        $role->setName($newName);
        $this->entityManager->flush();

        $io->success("Role '{$oldName}' renamed to '{$newName}' successfully.");
        return Command::SUCCESS;
    }

    private function extendRole(SymfonyStyle $io, ?string $name, InputInterface $input): int
    {
        if ($name === null) {
            $io->error('Role name is required for extend action.');
            return Command::INVALID;
        }

        $extendsName = $input->getOption('extends');
        $extendsName = is_string($extendsName) ? $extendsName : null;

        if ($extendsName === null) {
            $io->error('Role to extend is required for extend action.');
            return Command::INVALID;
        }

        if ($name === $extendsName) {
            $io->error('A role cannot extend itself.');
            return Command::INVALID;
        }

        $roleRepository = $this->entityManager->getRepository(Role::class);
        $role = $roleRepository->findOneBy(['name' => $name]);
        $extendsRole = $roleRepository->findOneBy(['name' => $extendsName]);

        if ($role === null) {
            $io->error("Role '{$name}' not found.");
            return Command::FAILURE;
        }

        if ($extendsRole === null) {
            $io->error("Role '{$extendsName}' not found.");
            return Command::FAILURE;
        }

        if ($role->extendsRole($extendsRole)) {
            $io->error("Role '{$name}' already extends '{$extendsName}'.");
            return Command::FAILURE;
        }

        // Check for circular dependencies
        if ($this->wouldCreateCircularDependency($role, $extendsRole)) {
            $io->error("Cannot extend '{$extendsName}': would create circular dependency.");
            return Command::FAILURE;
        }

        $role->extendRole($extendsRole);
        $this->entityManager->flush();

        $io->success("Role '{$name}' now extends '{$extendsName}' successfully.");
        return Command::SUCCESS;
    }

    private function unextendRole(SymfonyStyle $io, ?string $name, InputInterface $input): int
    {
        if ($name === null) {
            $io->error('Role name is required for unextend action.');
            return Command::INVALID;
        }

        $extendsName = $input->getOption('extends');
        $extendsName = is_string($extendsName) ? $extendsName : null;

        if ($extendsName === null) {
            $io->error('Role to unextend is required for unextend action.');
            return Command::INVALID;
        }

        $roleRepository = $this->entityManager->getRepository(Role::class);
        $role = $roleRepository->findOneBy(['name' => $name]);
        $extendsRole = $roleRepository->findOneBy(['name' => $extendsName]);

        if ($role === null) {
            $io->error("Role '{$name}' not found.");
            return Command::FAILURE;
        }

        if ($extendsRole === null) {
            $io->error("Role '{$extendsName}' not found.");
            return Command::FAILURE;
        }

        if (!$role->extendsRole($extendsRole)) {
            $io->error("Role '{$name}' does not extend '{$extendsName}'.");
            return Command::FAILURE;
        }

        $role->removeExtendedRole($extendsRole);
        $this->entityManager->flush();

        $io->success("Role '{$name}' no longer extends '{$extendsName}'.");
        return Command::SUCCESS;
    }

    /**
     * Check if extending a role would create a circular dependency.
     */
    private function wouldCreateCircularDependency(Role $role, Role $extendsRole): bool
    {
        // Check if the role to be extended already extends the current role
        return $extendsRole->extendsRole($role);
    }

    private function showHelp(SymfonyStyle $io, string $action): int
    {
        $io->error("Unknown action '{$action}'.");
        $io->text('Available actions: list, create, delete, show, users, rename, extend, unextend');
        return Command::INVALID;
    }
}
