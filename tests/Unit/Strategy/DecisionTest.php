<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit\Strategy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RickRole\Strategy\Decision;
use RickRole\Tests\Unit\Fixture\ReasonCode;
use RickRole\Voter\VoteResult;

/**
 * Unit tests for the Decision class.
 */
final class DecisionTest extends TestCase
{
    #[Test]
    public function it_takes_the_decision_and_code_from_the_deciding_vote(): void
    {
        $vote = VoteResult::deny('MFA is mandatory', code: ReasonCode::Mandatory);

        $decision = Decision::byVote($vote, 'Deny wins - MFA is mandatory');

        self::assertSame($vote, $decision->decidingVote);
        self::assertTrue($decision->result->isDeny());
        self::assertSame('Deny wins - MFA is mandatory', $decision->result->message());
        self::assertSame(ReasonCode::Mandatory, $decision->result->code());
        self::assertTrue($decision->hasDecidingVote());
        self::assertFalse($decision->hasNoDecidingVote());
    }

    #[Test]
    public function it_uses_the_deciding_votes_message_by_default(): void
    {
        $decision = Decision::byVote(VoteResult::allow('Role allows it', code: 'role.allow'));

        self::assertTrue($decision->result->isAllow());
        self::assertSame('Role allows it', $decision->result->message());
        self::assertSame('role.allow', $decision->result->code());
    }

    #[Test]
    public function it_cannot_be_decided_by_an_abstaining_vote(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('An abstaining vote cannot decide');

        Decision::byVote(VoteResult::abstain('No opinion'));
    }

    #[Test]
    public function it_can_be_decided_by_the_strategy_itself(): void
    {
        $result = VoteResult::deny('All voters abstained', code: 'no.decision');

        $decision = Decision::byStrategy($result);

        self::assertSame($result, $decision->result);
        self::assertNull($decision->decidingVote);
        self::assertFalse($decision->hasDecidingVote());
        self::assertTrue($decision->hasNoDecidingVote());
    }

    #[Test]
    public function it_must_allow_or_deny_when_the_strategy_decides(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A decision must allow or deny, not abstain');

        Decision::byStrategy(VoteResult::abstain('No opinion'));
    }
}
