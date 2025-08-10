<?php

declare(strict_types=1);

namespace RickRole\Strategy;

use RickRole\Voter\VoteResult;

/**
 * Deny Wins Strategy (Default).
 *
 * Priority: ABSTAIN < ALLOW < DENY
 *
 * - If any voter returns DENY, access is denied
 * - If no voter returns DENY but at least one returns ALLOW, access is granted
 * - If all voters return ABSTAIN, access is denied (fail secure)
 */
final class DenyWinsStrategy implements StrategyInterface
{
    public function decide(array $voteResults): VoteResult
    {
        // Handle empty votes
        if (empty($voteResults)) {
            return VoteResult::abstain('No votes provided');
        }

        $denyVote = null;
        $allowVote = null;

        foreach ($voteResults as $result) {
            if ($result->isDeny()) {
                $denyVote = $result;
                // In DenyWins strategy, we can return immediately on first deny
                return VoteResult::deny('Deny wins - ' . $result->message());
            } elseif ($result->isAllow() && $allowVote === null) {
                $allowVote = $result;
            }
        }

        // ALLOW wins over ABSTAIN
        if ($allowVote !== null) {
            return VoteResult::allow('No deny votes found - ' . $allowVote->message());
        }

        // All abstained - fail secure
        return VoteResult::abstain('All voters abstained');
    }

    public function shouldStopVoting(VoteResult $voteResult): bool
    {
        // Stop immediately on DENY since it cannot be overridden
        return $voteResult->isDeny();
    }
}
