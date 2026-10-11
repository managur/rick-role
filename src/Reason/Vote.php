<?php

declare(strict_types=1);

namespace RickRole\Reason;

use RickRole\Voter\VoterInterface;
use RickRole\Voter\VoteResult;

/**
 * A single voter's vote in a permission decision.
 */
final readonly class Vote
{
    /**
     * @param class-string<VoterInterface> $voter The class of the voter that cast this vote
     * @param string $decision One of VoteResult::ALLOW, VoteResult::DENY or VoteResult::ABSTAIN
     */
    public function __construct(
        public string $voter,
        public string $decision,
        public string $message,
        public string|\BackedEnum|null $code = null
    ) {
    }

    /**
     * Create a vote from the result the voter returned.
     */
    public static function cast(VoterInterface $voter, VoteResult $result): self
    {
        return new self($voter::class, $result->decision(), $result->message(), $result->code());
    }

    /**
     * Check if this vote allowed.
     */
    public function isAllow(): bool
    {
        return $this->decision === VoteResult::ALLOW;
    }

    /**
     * Check if this vote denied.
     */
    public function isDeny(): bool
    {
        return $this->decision === VoteResult::DENY;
    }

    /**
     * Check if this vote abstained.
     */
    public function isAbstain(): bool
    {
        return $this->decision === VoteResult::ABSTAIN;
    }
}
