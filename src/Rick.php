<?php

declare(strict_types=1);

namespace RickRole;

use RickRole\Exception\ConfigurationException;
use RickRole\Reason\Reason;

/**
 * The main Rick-Role client for checking permissions.
 *
 * This client provides the primary interface for permission checking through
 * allows() and disallows() methods. It uses a configurable voter stack to make
 * permission decisions.
 */
final class Rick
{
    public function __construct(
        private readonly Configuration $configuration
    ) {
        $this->validateConfiguration();
    }

    /**
     * Validate the configuration and throw ConfigurationException if invalid.
     *
     * @throws ConfigurationException If the configuration is invalid
     */
    private function validateConfiguration(): void
    {
        $voters = $this->configuration->voters();

        if (empty($voters)) {
            throw new ConfigurationException('No voters configured - Rick-Role requires at least one voter to function properly');
        }
    }

    /**
     * Check if a user is allowed to perform a specific action.
     *
     * @param string|int $userId The user identifier to check permissions for
     * @param string|\Stringable $to The action or permission being requested
     * @param mixed $onThis Optional subject/object for context-aware permissions
     * @param-out Reason $because Reason object passed by reference for detailed decision info
     * @return bool True if the action is allowed, false otherwise
     * @throws ConfigurationException If the voter stack is not properly configured
     *
     * @example
     * // Basic permission check
     * if ($rick->allows(userId: 123, to: 'create post')) {
     *     // User can create posts
     * }
     *
     * // Context-aware permission check
     * if ($rick->allows(userId: 123, to: 'edit post', onThis: $post)) {
     *     // User can edit this specific post
     * }
     */
    public function allows(
        string|int $userId,
        string|\Stringable $to,
        mixed $onThis = null,
        ?Reason &$because = null
    ): bool {
        $startTime = microtime(true);
        $voters = $this->configuration->voters();
        $logger = $this->configuration->logger();



        $strategy = $this->configuration->strategy();
        $voteResults = [];
        $voterDecisions = [];

        foreach ($voters as $voter) {
            $voteResult = $voter->vote($userId, $to, $onThis);
            $voteResults[] = $voteResult;

            $voterDecisions[] = [
                'voter' => $voter::class,
                'decision' => $voteResult->decision(),
                'message' => $voteResult->message()
            ];

            // Log individual voter decisions at DEBUG level
            $logger->debug('Voter decision', [
                'user_id' => $userId,
                'permission' => (string)$to,
                'voter' => $voter::class,
                'decision' => $voteResult->decision(),
                'message' => $voteResult->message()
            ]);

            // Create reason chain
            $voteReason = new Reason(
                userId: $userId,
                permission: (string)$to,
                subject: $onThis,
                voter: $voter::class,
                previous: $because
            );
            $voteReason->addDecision($voteResult->decision(), $voteResult->message());

            $because = $voteReason;

            // Apply strategy-specific logic
            if ($strategy->shouldStopVoting($voteResult)) {
                break;
            }
        }

        $finalDecisionResult = $strategy->decide($voteResults);

        // Update the final reason with the overall decision
        $finalReason = new Reason(
            userId: $userId,
            permission: (string)$to,
            subject: $onThis,
            voter: self::class,
            previous: $because
        );
        $finalReason->addDecision($finalDecisionResult->decision(), $finalDecisionResult->message());
        $because = $finalReason;

        $isAllowed = $finalDecisionResult->isAllow();
        $duration = $this->calculateDuration($startTime);

        // Log final decision at INFO or WARNING level
        $logLevel = $isAllowed ? 'info' : 'warning';
        $logger->log($logLevel, 'Permission check completed', [
            'user_id' => $userId,
            'permission' => (string)$to,
            'subject' => $this->formatSubject($onThis),
            'decision' => $finalDecisionResult->decision(),
            'allowed' => $isAllowed,
            'duration_ms' => $duration,
            'voter_count' => count($voters),
            'voter_decisions' => $voterDecisions,
            'strategy' => $strategy::class,
            'reason' => $finalDecisionResult->message()
        ]);

        return $isAllowed;
    }

    /**
     * Check if a user is disallowed from performing a specific action.
     *
     * This is simply the inverse of allows().
     *
     * @param string|int $userId The user identifier to check permissions for
     * @param string|\Stringable $to The action or permission being requested
     * @param mixed $onThis Optional subject/object for context-aware permissions
     * @param-out Reason $because Reason object passed by reference for detailed decision info
     * @return bool True if the action is disallowed, false otherwise
     *
     * @example
     * // Basic permission check
     * if ($rick->disallows(userId: 123, to: 'admin access')) {
     *     // User cannot access admin
     * }
     *
     * // Context-aware permission check
     * if ($rick->disallows(userId: 123, to: 'delete post', onThis: $post)) {
     *     // User cannot delete this specific post
     * }
     */
    public function disallows(
        string|int $userId,
        string|\Stringable $to,
        mixed $onThis = null,
        ?Reason &$because = null
    ): bool {
        return $this->allows($userId, $to, $onThis, $because) === false;
    }

    /**
     * Alias for disallows() - alternative reading preference.
     *
     * @param string|int $userId The user identifier to check permissions for
     * @param string|\Stringable $to The action or permission being requested
     * @param mixed $onThis Optional subject/object for context-aware permissions
     * @param-out Reason $because Reason object passed by reference for detailed decision info
     * @return bool True if the action is not allowed, false otherwise
     *
     * @example
     * // Alternative reading preference
     * if ($rick->doesNotAllow(userId: 123, to: 'admin access')) {
     *     // User does not have admin access
     * }
     */
    public function doesNotAllow(
        string|int $userId,
        string|\Stringable $to,
        mixed $onThis = null,
        ?Reason &$because = null
    ): bool {
        return $this->disallows($userId, $to, $onThis, $because);
    }

    /**
     * Calculate the duration of the permission check in milliseconds.
     */
    private function calculateDuration(float $startTime): float
    {
        return round((microtime(true) - $startTime) * 1000, 2);
    }

    /**
     * Format the subject for logging, handling different types safely.
     */
    private function formatSubject(mixed $subject): string
    {
        if ($subject === null) {
            return 'null';
        }

        if (is_string($subject)) {
            return $subject;
        }

        if (is_numeric($subject)) {
            return (string)$subject;
        }

        if (is_object($subject)) {
            return get_class($subject) . '#' . spl_object_id($subject);
        }

        if (is_array($subject)) {
            return 'array(' . count($subject) . ')';
        }

        return gettype($subject);
    }
}
