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
 * - If all voters return ABSTAIN, or there are no votes, access is denied (fail secure)
 */
final class AllowWinsStrategy implements StrategyInterface
{
    public function decide(array $voteResults): Decision
    {
        // Fail secure when there is nothing to decide on
        if (empty($voteResults)) {
            return Decision::byStrategy(VoteResult::deny('No votes provided'));
        }

        $denyVote = null;

        foreach ($voteResults as $result) {
            if ($result->isAllow()) {
                // In AllowWins strategy, we can return immediately on first allow
                return Decision::byVote($result, 'Allow wins - ' . $result->message());
            } elseif ($result->isDeny() && $denyVote === null) {
                $denyVote = $result;
            }
        }

        // DENY wins over ABSTAIN
        if ($denyVote !== null) {
            return Decision::byVote($denyVote, 'No allow votes - ' . $denyVote->message());
        }

        // All abstained - fail secure
        return Decision::byStrategy(VoteResult::deny('All voters abstained'));
    }

    public function shouldStopVoting(VoteResult $voteResult): bool
    {
        // Stop immediately on ALLOW since it cannot be overridden
        return $voteResult->isAllow();
    }
}
