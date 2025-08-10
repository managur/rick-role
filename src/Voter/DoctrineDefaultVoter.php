<?php

declare(strict_types=1);

namespace RickRole\Voter;

use Doctrine\ORM\EntityManagerInterface;
use RickRole\Entity\UserRole;
use RickRole\Strategy\StrategyInterface;

/**
 * Default voter that checks role-based permissions.
 *
 * This voter checks if a user has a role that contains the requested permission.
 * It works directly with user IDs and UserRole entities, eliminating the need for a User entity.
 * The voter uses the provided strategy to make informed decisions about ALLOW/DENY permissions.
 */
final class DoctrineDefaultVoter implements VoterInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StrategyInterface $strategy
    ) {
    }

    public function vote(
        string|int $userId,
        string|\Stringable $permission,
        mixed $subject = null
    ): VoteResult {
        try {
            $userRoleRepository = $this->entityManager->getRepository(UserRole::class);

            // Find all valid (non-expired) role assignments for this user
            $userRoles = $userRoleRepository->findBy([
                'userId' => (string)$userId
            ]);

            if (empty($userRoles)) {
                return VoteResult::deny('User not found or has no roles');
            }

            $voteResults = [];
            $permissionString = (string)$permission;
            $allPermissionDecisions = [];

            // Collect ALL permission decisions from all valid roles (including inherited permissions)
            foreach ($userRoles as $userRole) {
                // Skip expired role assignments
                if ($userRole->isValid() === false) {
                    continue;
                }

                $role = $userRole->getRole();

                // Get all permission decisions from this role (including inherited ones)
                $roleDecisions = $role->getAllPermissionDecisions();
                $allPermissionDecisions = array_merge($allPermissionDecisions, $roleDecisions);
            }

            // Filter decisions for the requested permission
            $permissionDecisions = array_filter(
                $allPermissionDecisions,
                fn($decision) => $decision['permission'] === $permissionString
            );

            // If no decisions found for this permission, deny access
            if (empty($permissionDecisions)) {
                return VoteResult::deny('User does not have permission: ' . $permissionString);
            }

            // Create vote results for each decision (this is what the strategy will evaluate)
            foreach ($permissionDecisions as $decision) {
                if ($decision['decision'] === 'ALLOW') {
                    $voteResults[] = VoteResult::allow('User has ALLOW permission through role: ' . $decision['source']);
                } elseif ($decision['decision'] === 'DENY') {
                    $voteResults[] = VoteResult::deny('User has DENY permission through role: ' . $decision['source']);
                } else {
                    // Unknown decision type - abstain
                    $voteResults[] = VoteResult::abstain('Unknown permission decision: ' . $decision['decision'] . ' from role: ' . $decision['source']);
                }
            }

            // Use the strategy to decide the final result based on all collected votes
            return $this->strategy->decide($voteResults);
        } catch (\Exception $e) {
            // Log the exception in a real application
            return VoteResult::deny('Error checking user permissions: ' . $e->getMessage());
        }
    }
}
