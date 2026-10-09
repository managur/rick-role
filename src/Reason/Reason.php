<?php

declare(strict_types=1);

namespace RickRole\Reason;

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
    private(set) string|\BackedEnum|null $lastCode = null;
    private ?string $lastMessage = null;
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
        private(set) ?Reason $previous = null,
        private(set) string|\BackedEnum|null $code = null {
            get {
                return $this->code ?? $this->lastCode;
            }
        },
        private readonly ?Reason $decidingVote = null
    ) {
    }

    /**
     * Add a decision entry to the chain.
     */
    public function addDecision(string $decision, string $reason, string|\BackedEnum|null $code = null): self
    {
        $this->lastDecision = $decision;
        $this->lastCode = $code;
        $this->lastMessage = $reason;
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
     * Check if the reason carries the given code.
     */
    public function is(string|\BackedEnum $code): bool
    {
        return $this->code === $code;
    }

    /**
     * Check if the reason does not carry the given code.
     */
    public function isNot(string|\BackedEnum $code): bool
    {
        return $this->is($code) === false;
    }

    /**
     * Get the message given by the deciding vote.
     */
    public function given(): ?string
    {
        $given = $this->decidingVote ?? $this;

        return $given->lastMessage ?? $given->message;
    }

    /**
     * Check if the given voter cast the deciding vote.
     */
    public function wasDecidedBy(string $voter): bool
    {
        return $this->decidingVote?->voter === $voter;
    }

    /**
     * Check if the given voter did not cast the deciding vote.
     */
    public function wasNotDecidedBy(string $voter): bool
    {
        return $this->wasDecidedBy($voter) === false;
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

    /** @return array{chain: array<string|null>, message: ?string, code: string|\BackedEnum|null, voters: array<VoterInterface>} */
    public function getFullTrace(): array
    {
        return [
            'chain' => $this->chain,
            'message' => $this->message,
            'code' => $this->code,
            'voters' => $this->voters,
        ];
    }
}
