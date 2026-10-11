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
 * - If all voters return ABSTAIN, or there are no votes, access is denied (fail secure)
 */
final class DenyWinsStrategy implements StrategyInterface
{
    public function decide(array $voteResults): Decision
    {
        // Fail secure when there is nothing to decide on
        if (empty($voteResults)) {
            return Decision::byStrategy(VoteResult::deny('No votes provided'));
        }

        $allowVote = null;

        foreach ($voteResults as $result) {
            if ($result->isDeny()) {
                // In DenyWins strategy, we can return immediately on first deny
                return Decision::byVote($result, 'Deny wins - ' . $result->message());
            } elseif ($result->isAllow() && $allowVote === null) {
                $allowVote = $result;
            }
        }

        // ALLOW wins over ABSTAIN
        if ($allowVote !== null) {
            return Decision::byVote($allowVote, 'No deny votes found - ' . $allowVote->message());
        }

        // All abstained - fail secure
        return Decision::byStrategy(VoteResult::deny('All voters abstained'));
    }

    public function shouldStopVoting(VoteResult $voteResult): bool
    {
        // Stop immediately on DENY since it cannot be overridden
        return $voteResult->isDeny();
    }
}
