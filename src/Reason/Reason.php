<?php

declare(strict_types=1);

namespace RickRole\Reason;

use RickRole\Strategy\StrategyInterface;
use RickRole\Voter\VoterInterface;

/**
 * Represents the reason for a permission decision.
 *
 * This class provides detailed information about why a permission was granted
 * or denied, including which voter made the decision and supports chaining
 * like PHP exceptions.
 */
final class Reason
{
    /** @var array<string> */
    private(set) array $chain = [];
    private(set) ?string $lastDecision = null;
    /** @var array<VoterInterface> */
    private(set) array $voters = [];

    public function __construct(
        private(set) string|int $userId,
        private(set) string $permission,
        private(set) mixed $subject = null,
        private(set) ?string $voter = null,
        private(set) ?string $decision = null {
            get {
                return $this->decision ?? $this->lastDecision;
            }
        },
        private(set) ?string $message = null {
            get {
                return $this->message ?? (end($this->chain) ?: null);
            }
        },
        private(set) ?Reason $previous = null
    ) {
    }

    /**
     * Add a decision entry to the chain.
     */
    public function addDecision(string $decision, string $reason): self
    {
        $this->lastDecision = $decision;
        $this->chain[] = "[$decision] $reason";
        return $this;
    }

    public function addVoter(VoterInterface $voter): self
    {
        $this->voters[] = $voter;
        return $this;
    }

    /**
     * Get a string representation of the full reason chain.
     */
    public function trace(): string
    {
        return implode("\n", $this->chain);
    }

    /**
     * Check if the last decision represents an ALLOW.
     */
    public function isAllow(): bool
    {
        return strtoupper($this->lastDecision ?? '') === 'ALLOW';
    }

    /**
     * Check if the last decision represents a DENY.
     */
    public function isDeny(): bool
    {
        return strtoupper($this->lastDecision ?? '') === 'DENY';
    }

    /**
     * Check if the last decision represents an ABSTAIN.
     */
    public function isAbstain(): bool
    {
        return strtoupper($this->lastDecision ?? '') === 'ABSTAIN';
    }



    /**
     * Convert the reason to a string.
     */
    public function __toString(): string
    {
        return sprintf(
            'Permission "%s" for user "%s" was %s by %s: %s',
            $this->permission,
            $this->userId,
            strtolower((string) $this->decision),
            $this->voter ?? 'Unknown',
            $this->message ?? 'No message'
        );
    }

    /** @return array{chain: array<string|null>, message: ?string, voters: array<VoterInterface>} */
    public function getFullTrace(): array
    {
        return [
            'chain' => $this->chain,
            'message' => $this->message,
            'voters' => $this->voters,
        ];
    }
}
