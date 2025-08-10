<?php

declare(strict_types=1);

namespace RickRole;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RickRole\Strategy\StrategyInterface;
use RickRole\Strategy\DenyWinsStrategy;
use RickRole\Voter\VoterInterface;

/**
 * Configuration class for Rick-Role client.
 *
 * Manages the voter stack and voting strategy configuration.
 */
final class Configuration
{
    /** @var VoterInterface[] */
    private array $voters = [];

    private LoggerInterface $logger;

    private StrategyInterface $strategy;

    public function __construct()
    {
        $this->logger = new NullLogger();
        $this->strategy = new DenyWinsStrategy();
        // Note: DoctrineDefaultVoter requires EntityManager, so it's not added by default
        // Users should add their own voters with proper EntityManager configuration
    }

    /**
     * Add a voter to the stack.
     *
     * Voters are processed in the order they are added.
     */
    public function addVoter(VoterInterface $voter): self
    {
        $this->voters[] = $voter;
        return $this;
    }

    /**
     * Set multiple voters at once, replacing any existing voters.
     *
     * @param VoterInterface[] $voters
     */
    public function setVoters(array $voters): self
    {
        $this->voters = [];
        foreach ($voters as $voter) {
            $this->addVoter($voter);
        }
        return $this;
    }

    /**
     * Get all configured voters.
     *
     * @return VoterInterface[]
     */
    public function voters(): array
    {
        return $this->voters;
    }

    /**
     * Set the voting strategy.
     */
    public function setStrategy(StrategyInterface $strategy): self
    {
        $this->strategy = $strategy;
        return $this;
    }

    /**
     * Get the current voting strategy.
     */
    public function strategy(): StrategyInterface
    {
        return $this->strategy;
    }

    /**
     * Set a PSR-3 logger for audit logging.
     *
     * When a logger is provided, Rick-Role will log permission checks at appropriate levels:
     * - INFO: Final permission decisions (allow/deny)
     * - DEBUG: Detailed voter decisions and reasoning chains
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Get the configured logger
     */
    public function logger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Clear all voters from the stack.
     */
    public function clearVoters(): self
    {
        $this->voters = [];
        return $this;
    }

    /**
     * Get the number of voters in the stack.
     */
    public function voterCount(): int
    {
        return count($this->voters);
    }
}
