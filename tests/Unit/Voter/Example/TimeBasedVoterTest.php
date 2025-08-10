<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit\Voter\Example;

use PHPUnit\Framework\TestCase;
use RickRole\Voter\Example\TimeBasedVoter;
use Symfony\Component\Clock\MockClock;

final class TimeBasedVoterTest extends TestCase
{
    public function testAllowsDuringBusinessHours(): void
    {
        $clock = new MockClock('2025-01-01 10:00:00');
        $voter = new TimeBasedVoter($clock, '09:00', '17:00', 'UTC');

        $result = $voter->vote('user', 'any');
        self::assertTrue($result->isAllow());
    }

    public function testDeniesOutsideBusinessHours(): void
    {
        $clock = new MockClock('2025-01-01 20:00:00');
        $voter = new TimeBasedVoter($clock, '09:00', '17:00', 'UTC');

        $result = $voter->vote('user', 'any');
        self::assertTrue($result->isDeny());
    }

    public function testAllowsWithMinutePrecision(): void
    {
        $clock = new MockClock('2025-01-01 09:15:00');
        $voter = new TimeBasedVoter($clock, '09:00', '09:30', 'UTC');

        $result = $voter->vote('user', 'any');
        self::assertTrue($result->isAllow());
    }

    public function testBoundaryInclusiveStartAndEnd(): void
    {
        $startClock = new MockClock('2025-01-01 13:00:00');
        $endClock = new MockClock('2025-01-01 13:30:00');
        $voterStart = new TimeBasedVoter($startClock, '13:00', '13:30', 'UTC');
        $voterEnd = new TimeBasedVoter($endClock, '13:00', '13:30', 'UTC');

        self::assertTrue($voterStart->vote('user', 'any')->isAllow());
        self::assertTrue($voterEnd->vote('user', 'any')->isAllow());
    }

    public function testInvalidTimesFallbackToDeny(): void
    {
        $clock = new MockClock('2025-01-01 10:00:00');
        // Invalid formats should fallback to 00:00-00:00 window
        $voter = new TimeBasedVoter($clock, 'invalid', 'oops', 'UTC');

        $result = $voter->vote('user', 'any');
        self::assertTrue($result->isDeny());
    }

    public function testSameStartAndEndOnlyAllowsAtThatMinute(): void
    {
        $allowClock = new MockClock('2025-01-01 12:00:00');
        $denyClock = new MockClock('2025-01-01 12:01:00');
        $voterAllow = new TimeBasedVoter($allowClock, '12:00', '12:00', 'UTC');
        $voterDeny = new TimeBasedVoter($denyClock, '12:00', '12:00', 'UTC');

        self::assertTrue($voterAllow->vote('user', 'any')->isAllow());
        self::assertTrue($voterDeny->vote('user', 'any')->isDeny());
    }

    public function testCrossMidnightAllowsBeforeMidnight(): void
    {
        $clock = new MockClock('2025-01-01 23:30:00');
        $voter = new TimeBasedVoter($clock, '23:00', '01:00', 'UTC');

        $result = $voter->vote('user', 'any');
        self::assertTrue($result->isAllow());
    }

    public function testCrossMidnightAllowsAfterMidnight(): void
    {
        $clock = new MockClock('2025-01-02 00:30:00');
        $voter = new TimeBasedVoter($clock, '23:00', '01:00', 'UTC');

        $result = $voter->vote('user', 'any');
        self::assertTrue($result->isAllow());
    }

    public function testCrossMidnightDeniesOutsideWindow(): void
    {
        $clockEarly = new MockClock('2025-01-01 22:00:00');
        $clockLate = new MockClock('2025-01-02 02:00:00');
        $voterEarly = new TimeBasedVoter($clockEarly, '23:00', '01:00', 'UTC');
        $voterLate = new TimeBasedVoter($clockLate, '23:00', '01:00', 'UTC');

        self::assertTrue($voterEarly->vote('user', 'any')->isDeny());
        self::assertTrue($voterLate->vote('user', 'any')->isDeny());
    }
}
