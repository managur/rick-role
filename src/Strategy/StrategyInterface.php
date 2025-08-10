<?php

declare(strict_types=1);

namespace RickRole\Strategy;

use RickRole\Voter\VoteResult;

/**
 * Interface for voting strategies.
 *
 * Strategies determine how voter results are combined to make
 * the final permission decision.
 */
interface StrategyInterface
{
    /**
     * Make the final decision based on all vote results.
     *
     * @param VoteResult[] $voteResults Array of vote results from all voters
     * @return VoteResult The final voting decision
     */
    public function decide(array $voteResults): VoteResult;

    /**
     * Determine if voting should stop early based on a vote result.
     *
     * This allows strategies to optimize by stopping the voter chain
     * when a definitive decision can be made.
     *
     * @param VoteResult $voteResult The current vote result
     * @return bool True if voting should stop, false to continue
     */
    public function shouldStopVoting(VoteResult $voteResult): bool;
}
