<?php

declare(strict_types=1);

namespace RickRole\Voter;

/**
 * Interface for all Rick-Role voters.
 *
 * Voters are responsible for making permission decisions based on
 * user, permission, and optional subject context.
 */
interface VoterInterface
{
    /**
     * Make a voting decision for the given parameters.
     *
     * @param string|int $userId The user identifier
     * @param string|\Stringable $permission The permission being checked
     * @param mixed $subject Optional subject for context-aware permissions
     * @return VoteResult The voting result (ALLOW, DENY, or ABSTAIN)
     */
    public function vote(
        string|int $userId,
        string|\Stringable $permission,
        mixed $subject = null
    ): VoteResult;
}
