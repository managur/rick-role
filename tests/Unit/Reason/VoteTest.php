<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit\Reason;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RickRole\Reason\Vote;
use RickRole\Tests\Unit\Fixture\ReasonCode;
use RickRole\Voter\Example\IpAddressVoter;
use RickRole\Voter\VoteResult;

/**
 * Unit tests for the Vote class.
 */
final class VoteTest extends TestCase
{
    #[Test]
    public function it_is_cast_from_a_voter_and_its_result(): void
    {
        $voter = new IpAddressVoter(['127.0.0.1']);

        $vote = Vote::cast($voter, VoteResult::deny('MFA is mandatory', code: ReasonCode::Mandatory));

        self::assertSame(IpAddressVoter::class, $vote->voter);
        self::assertSame('deny', $vote->decision);
        self::assertSame('MFA is mandatory', $vote->message);
        self::assertSame(ReasonCode::Mandatory, $vote->code);
    }

    /** @return array<string, array{VoteResult, bool, bool, bool}> */
    public static function decisions(): array
    {
        return [
            'allow' => [VoteResult::allow('Allowed'), true, false, false],
            'deny' => [VoteResult::deny('Denied'), false, true, false],
            'abstain' => [VoteResult::abstain('Abstained'), false, false, true],
        ];
    }

    #[DataProvider('decisions')]
    #[Test]
    public function it_knows_its_decision(VoteResult $result, bool $allow, bool $deny, bool $abstain): void
    {
        $vote = Vote::cast(new IpAddressVoter([]), $result);

        self::assertSame($allow, $vote->isAllow());
        self::assertSame($deny, $vote->isDeny());
        self::assertSame($abstain, $vote->isAbstain());
        self::assertNull($vote->code);
    }
}
