<?php

declare(strict_types=1);

namespace RickRole\Voter;

/**
 * Represents the result of a voter's decision.
 *
 * This class encapsulates the three possible voter decisions: ALLOW, DENY, or ABSTAIN.
 */
final class VoteResult
{
    public const ALLOW = 'allow';
    public const DENY = 'deny';
    public const ABSTAIN = 'abstain';

    private function __construct(
        private readonly string $result,
        private readonly string $message,
        private readonly ?VoterInterface $voter = null
    ) {
    }

    /**
     * Create an ALLOW vote result.
     */
    public static function allow(string $message, ?VoterInterface $voter = null): self
    {
        return new self(self::ALLOW, $message, $voter);
    }

    /**
     * Create a DENY vote result.
     */
    public static function deny(?string $message = 'Access denied', ?VoterInterface $voter = null): self
    {
        return new self(self::DENY, $message ?? 'Access denied', $voter);
    }

    /**
     * Create an ABSTAIN vote result.
     */
    public static function abstain(string $message, ?VoterInterface $voter = null): self
    {
        return new self(self::ABSTAIN, $message, $voter);
    }

    /**
     * Get the decision (ALLOW, DENY, or ABSTAIN).
     */
    public function result(): string
    {
        return $this->result;
    }

    /**
     * Get the message explaining the decision.
     */
    public function message(): string
    {
        return $this->message;
    }

    /**
     * Check if this is an ALLOW result.
     */
    public function isAllow(): bool
    {
        return $this->result === self::ALLOW;
    }

    /**
     * Check if this is a DENY result.
     */
    public function isDeny(): bool
    {
        return $this->result === self::DENY;
    }

    /**
     * Check if this is an ABSTAIN result.
     */
    public function isAbstain(): bool
    {
        return $this->result === self::ABSTAIN;
    }

    /**
     * Convert to string representation.
     */
    public function __toString(): string
    {
        return sprintf('%s: %s', strtoupper($this->result), $this->message);
    }

    public function voter(): ?VoterInterface
    {
        return $this->voter;
    }

    public function decision(): string
    {
        return $this->result;
    }
}
