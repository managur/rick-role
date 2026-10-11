<?php

declare(strict_types=1);

namespace RickRole\Strategy;

use RickRole\Voter\VoteResult;

/**
 * The outcome of a strategy combining vote results.
 *
 * A decision always allows or denies. Either one of the votes decided it, and the decision takes that vote's decision
 * and code, or the strategy decided by itself, for example because every voter abstained.
 */
final readonly class Decision
{
    private function __construct(
        public VoteResult $result,
        public ?VoteResult $decidingVote
    ) {
    }

    /**
     * Decide with one of the vote results the strategy was given.
     *
     * The decision and code come from that vote, so they cannot disagree with it. Pass the vote result itself, not a
     * copy: Rick finds the deciding voter by identity, so filtering or sorting the vote results first is safe.
     *
     * @param VoteResult $vote The deciding vote, which must allow or deny
     * @param string|null $message The final message, or null to use the vote's own message
     * @throws \InvalidArgumentException If the vote abstained
     */
    public static function byVote(VoteResult $vote, ?string $message = null): self
    {
        if ($vote->isAbstain()) {
            throw new \InvalidArgumentException('An abstaining vote cannot decide');
        }

        $message ??= $vote->message();
        $result = $vote->isAllow()
            ? VoteResult::allow($message, $vote->voter(), $vote->code())
            : VoteResult::deny($message, $vote->voter(), $vote->code());

        return new self($result, $vote);
    }

    /**
     * Decide without a deciding vote, for example when every voter abstained.
     *
     * @param VoteResult $result The final result, which must allow or deny
     * @throws \InvalidArgumentException If the result abstains
     */
    public static function byStrategy(VoteResult $result): self
    {
        if ($result->isAbstain()) {
            throw new \InvalidArgumentException('A decision must allow or deny, not abstain');
        }

        return new self($result, null);
    }

    /**
     * Check if the decision was made by a vote rather than by the strategy itself.
     *
     * @phpstan-assert-if-true !null $this->decidingVote
     */
    public function hasDecidingVote(): bool
    {
        return $this->decidingVote !== null;
    }

    /**
     * Check if no vote made the decision, so the strategy decided by itself.
     *
     * @phpstan-assert-if-false !null $this->decidingVote
     */
    public function hasNoDecidingVote(): bool
    {
        return $this->hasDecidingVote() === false;
    }
}
