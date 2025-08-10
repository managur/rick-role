<?php

declare(strict_types=1);

namespace RickRole\CLI\Command;

use Doctrine\ORM\EntityManagerInterface;
use RickRole\Entity\Role;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Permission management command for Rick-Role CLI.
 */
#[AsCommand(
    name: 'permission',
    description: 'Manage permissions within roles'
)]
final class PermissionCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action to perform (list, add, remove, toggle, allow, deny)')
            ->addOption('role', 'r', InputOption::VALUE_REQUIRED, 'Role name')
            ->addOption('permission', 'p', InputOption::VALUE_REQUIRED, 'Permission name')
            ->addOption('decision', 'd', InputOption::VALUE_REQUIRED, 'Decision type (ALLOW/DENY) for add action', 'ALLOW')
            ->setHelp(<<<'HELP'
The <info>permission</info> command manages permissions within roles.

Available actions:
  <info>list</info>                    - List all permissions for a role (requires -r)
  <info>add</info>                     - Add a permission to a role (requires -r and -p)
  <info>remove</info>                  - Remove a permission from a role (requires -r and -p)
  <info>toggle</info>                  - Toggle permission between ALLOW/DENY (requires -r and -p)
  <info>allow</info>                   - Set permission to ALLOW (requires -r and -p)
  <info>deny</info>                    - Set permission to DENY (requires -r and -p)

Examples:
  <info>rick permission list -r admin</info>
  <info>rick permission add -r admin -p create_user</info>
  <info>rick permission add -r admin -p delete_user -d DENY</info>
  <info>rick permission toggle -r admin -p edit_user</info>
  <info>rick permission allow -r admin -p view_logs</info>
  <info>rick permission deny -r admin -p access_admin</info>
  <info>rick permission remove -r admin -p old_permission</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $this->getStringArgument($input, 'action');
        $roleName = $input->getOption('role');
        $roleName = is_string($roleName) ? $roleName : null;
        $permission = $input->getOption('permission');
        $permission = is_string($permission) ? $permission : null;

        // Get the role
        $roleRepository = $this->entityManager->getRepository(Role::class);
        $role = $roleRepository->findOneBy(['name' => $roleName]);

        if ($role === null) {
            $io->error("Role '{$roleName}' not found.");
            return Command::FAILURE;
        }

        return match ($action) {
            'list' => $this->listPermissions($io, $role),
            'add' => $this->addPermission($io, $role, $permission, $input),
            'remove' => $this->removePermission($io, $role, $permission),
            'toggle' => $this->togglePermission($io, $role, $permission),
            'allow' => $this->allowPermission($io, $role, $permission),
            'deny' => $this->denyPermission($io, $role, $permission),
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

    private function listPermissions(SymfonyStyle $io, Role $role): int
    {
        $permissions = $role->getPermissions();

        if (empty($permissions)) {
            $io->info("No permissions assigned to role '{$role->name()}'.");
            return Command::SUCCESS;
        }

        $io->section("Permissions for role: {$role->name()}");

        $tableData = [];
        foreach ($permissions as $permission => $decision) {
            $tableData[] = [$permission, $decision];
        }

        $io->table(['Permission', 'Decision'], $tableData);

        return Command::SUCCESS;
    }

    private function addPermission(SymfonyStyle $io, Role $role, ?string $permission, InputInterface $input): int
    {
        if ($permission === null) {
            $io->error('Permission name is required for add action.');
            return Command::INVALID;
        }

        if ($role->hasPermission($permission)) {
            $io->error("Permission '{$permission}' already exists in role '{$role->name()}'.");
            return Command::FAILURE;
        }

        $decisionOption = $input->getOption('decision');
        $decision = strtoupper(is_string($decisionOption) ? $decisionOption : 'ALLOW');
        if (!in_array($decision, ['ALLOW', 'DENY'], true)) {
            $io->error('Decision must be either ALLOW or DENY.');
            return Command::INVALID;
        }

        if ($decision === 'ALLOW') {
            $role->allowPermission($permission);
        } else {
            $role->denyPermission($permission);
        }

        $this->entityManager->flush();

        $io->success("Permission '{$permission}' added to role '{$role->name()}' with decision '{$decision}'.");
        return Command::SUCCESS;
    }

    private function removePermission(SymfonyStyle $io, Role $role, ?string $permission): int
    {
        if ($permission === null) {
            $io->error('Permission name is required for remove action.');
            return Command::INVALID;
        }

        if (!$role->hasPermission($permission)) {
            $io->error("Permission '{$permission}' not found in role '{$role->name()}'.");
            return Command::FAILURE;
        }

        $role->removePermission($permission);
        $this->entityManager->flush();

        $io->success("Permission '{$permission}' removed from role '{$role->name()}'.");
        return Command::SUCCESS;
    }

    private function togglePermission(SymfonyStyle $io, Role $role, ?string $permission): int
    {
        if ($permission === null) {
            $io->error('Permission name is required for toggle action.');
            return Command::INVALID;
        }

        if (!$role->hasPermission($permission)) {
            $io->error("Permission '{$permission}' not found in role '{$role->name()}'.");
            return Command::FAILURE;
        }

        $currentDecision = $role->getPermissionDecision($permission);
        $newDecision = $currentDecision === 'ALLOW' ? 'DENY' : 'ALLOW';

        if ($newDecision === 'ALLOW') {
            $role->allowPermission($permission);
        } else {
            $role->denyPermission($permission);
        }

        $this->entityManager->flush();

        $io->success("Permission '{$permission}' toggled from '{$currentDecision}' to '{$newDecision}' in role '{$role->name()}'.");
        return Command::SUCCESS;
    }

    private function allowPermission(SymfonyStyle $io, Role $role, ?string $permission): int
    {
        if ($permission === null) {
            $io->error('Permission name is required for allow action.');
            return Command::INVALID;
        }

        $role->allowPermission($permission);
        $this->entityManager->flush();

        $io->success("Permission '{$permission}' set to ALLOW in role '{$role->name()}'.");
        return Command::SUCCESS;
    }

    private function denyPermission(SymfonyStyle $io, Role $role, ?string $permission): int
    {
        if ($permission === null) {
            $io->error('Permission name is required for deny action.');
            return Command::INVALID;
        }

        $role->denyPermission($permission);
        $this->entityManager->flush();

        $io->success("Permission '{$permission}' set to DENY in role '{$role->name()}'.");
        return Command::SUCCESS;
    }

    private function showHelp(SymfonyStyle $io, string $action): int
    {
        $io->error("Unknown action '{$action}'.");
        $io->text('Available actions: list, add, remove, toggle, allow, deny');
        return Command::INVALID;
    }
}
