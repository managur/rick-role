<?php

declare(strict_types=1);

namespace RickRole\Strategy;

use RickRole\Voter\VoteResult;

/**
 * Allow Wins Strategy.
 *
 * Priority: ABSTAIN < DENY < ALLOW
 *
 * - If any voter returns ALLOW, access is granted
 * - If no voter returns ALLOW but at least one returns DENY, access is denied
 * - If all voters return ABSTAIN, access is denied (fail secure)
 */
final class AllowWinsStrategy implements StrategyInterface
{
    public function decide(array $voteResults): VoteResult
    {
        // Handle empty votes
        if (empty($voteResults)) {
            return VoteResult::abstain('No votes provided');
        }

        $allowVote = null;
        $denyVote = null;

        foreach ($voteResults as $result) {
            if ($result->isAllow()) {
                $allowVote = $result;
                // In AllowWins strategy, we can return immediately on first allow
                return VoteResult::allow('Allow wins - ' . $result->message());
            } elseif ($result->isDeny() && $denyVote === null) {
                $denyVote = $result;
            }
        }

        // DENY wins over ABSTAIN
        if ($denyVote !== null) {
            return VoteResult::deny('No allow votes - ' . $denyVote->message());
        }

        // All abstained - fail secure
        return VoteResult::abstain('All voters abstained');
    }

    public function shouldStopVoting(VoteResult $voteResult): bool
    {
        // Stop immediately on ALLOW since it cannot be overridden
        return $voteResult->isAllow();
    }
}
