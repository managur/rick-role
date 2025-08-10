<?php

declare(strict_types=1);

namespace RickRole\Voter\Example;

use DateTimeZone;
use Psr\Clock\ClockInterface;
use RickRole\Voter\VoteResult;
use RickRole\Voter\VoterInterface;

/**
 * Example Voter: Time-based access control.
 *
 * Allows access only during the configured inclusive hour range.
 * This class is provided as an EXAMPLE voter and is not required for
 * regular library usage.
 */
final class TimeBasedVoter implements VoterInterface
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly string $startTime = '09:00',
        private readonly string $endTime = '17:00',
        private readonly string $timezone = 'UTC'
    ) {
    }

    /**
     * @param string|int $userId
     * @param string|\Stringable $permission
     * @param mixed $subject
     */
    public function vote(string|int $userId, string|\Stringable $permission, mixed $subject = null): VoteResult
    {
        // Normalize to configured timezone regardless of clock's internal TZ
        $tz = new DateTimeZone($this->timezone);
        $now = $this->clock->now()->setTimezone($tz);
        [$startHour, $startMinute] = $this->parseTime($this->startTime);
        [$endHour, $endMinute] = $this->parseTime($this->endTime);

        $start = $now->setTime((int) $startHour, (int) $startMinute, 0, 0);
        $end = $now->setTime((int) $endHour, (int) $endMinute, 0, 0);

        if ($end < $start) {
            // Window crosses midnight. Adjust either start or end based on current time.
            if ($now < $start) {
                $start = $start->modify('-1 day');
            } else {
                $end = $end->modify('+1 day');
            }
        }

        if ($now >= $start && $now <= $end) {
            return VoteResult::allow(
                sprintf(
                    'Access granted during business hours (%s-%s). Current: %s',
                    $start->format('H:i'),
                    $end->format('H:i'),
                    $now->format('H:i')
                )
            );
        }

        return VoteResult::deny(
            sprintf(
                'Access denied outside business hours (%s-%s). Current: %s',
                $start->format('H:i'),
                $end->format('H:i'),
                $now->format('H:i')
            )
        );
    }

    /**
     * @return array{0:int,1:int}
     */
    private function parseTime(string $time): array
    {
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $m) === 1) {
            return [(int) $m[1], (int) $m[2]];
        }
        // Fallback: treat invalid as 00:00
        return [0, 0];
    }
}
