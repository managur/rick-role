<?php

declare(strict_types=1);

namespace RickRole\CLI\Command;

use DateTimeImmutable;
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
 * User-role assignment management command for Rick-Role CLI.
 */
#[AsCommand(
    name: 'user',
    description: 'Manage user-role assignments'
)]
final class UserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform (assign, remove, roles, users)')
            ->addOption('user-id', 'u', InputOption::VALUE_REQUIRED, 'User ID')
            ->addOption('role', 'r', InputOption::VALUE_REQUIRED, 'Role name')
            ->addOption('expires-at', 'e', InputOption::VALUE_REQUIRED, 'Expiration date (YYYY-MM-DD HH:MM:SS) for role assignment')
            ->setHelp(<<<'HELP'
The <info>user</info> command manages user-role assignments.

Available actions:
  <info>assign</info>     - Assign a role to a user (requires -u and -r)
  <info>remove</info>     - Remove a role from a user (requires -u and -r)
  <info>roles</info>      - Show all roles assigned to a user (requires -u)
  <info>users</info>      - Show all users assigned to a role (requires -r)

Examples:
  <info>rick user assign -u user123 -r admin</info>
  <info>rick user assign -u user123 -r admin -e "2024-12-31 23:59:59"</info>
  <info>rick user roles -u user123</info>
  <info>rick user users -r admin</info>
  <info>rick user remove -u user123 -r old_role</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $this->getStringArgument($input, 'action');

        // Get user ID from option
        $userId = $input->getOption('user-id');
        $userId = is_string($userId) ? $userId : null;

        // Get role name from option
        $roleName = $input->getOption('role');
        $roleName = is_string($roleName) ? $roleName : null;

        return match ($action) {
            'assign' => $this->assignRole($io, $userId, $roleName, $input),
            'remove' => $this->removeRole($io, $userId, $roleName),
            'roles' => $this->showUserRoles($io, $userId),
            'users' => $this->showRoleUsers($io, $roleName),
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

    private function assignRole(SymfonyStyle $io, ?string $userId, ?string $roleName, InputInterface $input): int
    {
        if ($userId === null || $roleName === null) {
            $io->error('User ID and role name are required for assign action.');
            return Command::INVALID;
        }

        // Check if role exists
        $roleRepository = $this->entityManager->getRepository(Role::class);
        $role = $roleRepository->findOneBy(['name' => $roleName]);

        if ($role === null) {
            $io->error("Role '{$roleName}' not found.");
            return Command::FAILURE;
        }

        // Check if assignment already exists
        $userRoleRepository = $this->entityManager->getRepository(UserRole::class);
        $existingAssignment = $userRoleRepository->findOneBy([
            'userId' => $userId,
            'role' => $role
        ]);

        if ($existingAssignment !== null) {
            $io->error("User '{$userId}' already has role '{$roleName}' assigned.");
            return Command::FAILURE;
        }

        // Parse expiration date if provided
        $expiresAt = null;
        $expiresAtString = $input->getOption('expires-at');
        if ($expiresAtString !== null && is_string($expiresAtString)) {
            try {
                $expiresAt = new DateTimeImmutable($expiresAtString);
            } catch (\Exception $e) {
                $io->error("Invalid expiration date format. Use YYYY-MM-DD HH:MM:SS");
                return Command::INVALID;
            }
        }

        // Create the assignment
        $userRole = new UserRole($userId, $role, $expiresAt);
        $this->entityManager->persist($userRole);
        $this->entityManager->flush();

        $expiryText = $expiresAt !== null ? $expiresAt->format('Y-m-d H:i:s') : 'Never';
        $io->success("Role '{$roleName}' assigned to user '{$userId}' (expires: {$expiryText}).");
        return Command::SUCCESS;
    }

    private function removeRole(SymfonyStyle $io, ?string $userId, ?string $roleName): int
    {
        if ($userId === null || $roleName === null) {
            $io->error('User ID and role name are required for remove action.');
            return Command::INVALID;
        }

        // Check if role exists
        $roleRepository = $this->entityManager->getRepository(Role::class);
        $role = $roleRepository->findOneBy(['name' => $roleName]);

        if ($role === null) {
            $io->error("Role '{$roleName}' not found.");
            return Command::FAILURE;
        }

        // Find the assignment
        $userRoleRepository = $this->entityManager->getRepository(UserRole::class);
        $userRole = $userRoleRepository->findOneBy([
            'userId' => $userId,
            'role' => $role
        ]);

        if ($userRole === null) {
            $io->error("User '{$userId}' does not have role '{$roleName}' assigned.");
            return Command::FAILURE;
        }

        $this->entityManager->remove($userRole);
        $this->entityManager->flush();

        $io->success("Role '{$roleName}' removed from user '{$userId}'.");
        return Command::SUCCESS;
    }

    private function showUserRoles(SymfonyStyle $io, ?string $userId): int
    {
        if ($userId === null) {
            $io->error('User ID is required for roles action.');
            return Command::INVALID;
        }

        $userRoleRepository = $this->entityManager->getRepository(UserRole::class);
        $userRoles = $userRoleRepository->findBy(['userId' => $userId]);

        if (empty($userRoles)) {
            $io->info("User '{$userId}' has no roles assigned.");
            return Command::SUCCESS;
        }

        $io->section("Roles assigned to user: {$userId}");

        $tableData = [];
        foreach ($userRoles as $userRole) {
            $role = $userRole->getRole();
            $status = $userRole->isValid() ? 'Active' : 'Expired';
            $expiresAt = $userRole->getExpiresAt();
            $expiryText = $expiresAt !== null ? $expiresAt->format('Y-m-d H:i:s') : 'Never';

            $tableData[] = [
                $role->name(),
                $role->description() ?? 'No description',
                $userRole->getAssignedAt()->format('Y-m-d H:i:s'),
                $expiryText,
                $status,
            ];
        }

        $io->table(
            ['Role', 'Description', 'Assigned At', 'Expires At', 'Status'],
            $tableData
        );

        return Command::SUCCESS;
    }

    private function showRoleUsers(SymfonyStyle $io, ?string $roleName): int
    {
        if ($roleName === null) {
            $io->error('Role name is required for users action.');
            return Command::INVALID;
        }

        // Check if role exists
        $roleRepository = $this->entityManager->getRepository(Role::class);
        $role = $roleRepository->findOneBy(['name' => $roleName]);

        if ($role === null) {
            $io->error("Role '{$roleName}' not found.");
            return Command::FAILURE;
        }

        $userRoles = $role->getUserRoles();

        if ($userRoles->isEmpty()) {
            $io->info("No users assigned to role '{$roleName}'.");
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

    private function showHelp(SymfonyStyle $io, string $action): int
    {
        $io->error("Unknown action '{$action}'.");
        $io->text('Available actions: assign, remove, roles, users');
        return Command::INVALID;
    }
}
