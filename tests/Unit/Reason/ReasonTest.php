<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit\Reason;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RickRole\Reason\Reason;
use RickRole\Reason\Vote;
use RickRole\Tests\Unit\Fixture\ReasonCode;
use RickRole\Voter\Example\IpAddressVoter;
use RickRole\Voter\Example\TimeBasedVoter;

/**
 * Unit tests for the Reason class.
 */
final class ReasonTest extends TestCase
{
    #[Test]
    public function it_holds_the_decision(): void
    {
        $subject = new \stdClass();

        $reason = new Reason(123, 'edit post', $subject, 'allow', 'No deny votes found - Role allows it', 'role.allow');

        self::assertSame(123, $reason->userId);
        self::assertSame('edit post', $reason->permission);
        self::assertSame($subject, $reason->subject);
        self::assertSame('allow', $reason->decision);
        self::assertSame('No deny votes found - Role allows it', $reason->message);
        self::assertSame('role.allow', $reason->code);
        self::assertSame([], $reason->votes);
        self::assertNull($reason->decidingVote);
    }

    #[Test]
    public function it_allows(): void
    {
        $reason = new Reason(123, 'edit post', null, 'allow', 'Allowed');

        self::assertTrue($reason->isAllow());
        self::assertFalse($reason->isDeny());
    }

    #[Test]
    public function it_denies(): void
    {
        $reason = new Reason('user-1', 'edit post', null, 'deny', 'Denied');

        self::assertFalse($reason->isAllow());
        self::assertTrue($reason->isDeny());
    }

    /** @return array<string, array{string}> */
    public static function invalidDecisions(): array
    {
        return [
            'abstain' => ['abstain'],
            'uppercase allow' => ['ALLOW'],
            'unknown' => ['maybe'],
        ];
    }

    #[DataProvider('invalidDecisions')]
    #[Test]
    public function it_must_allow_or_deny(string $decision): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('A reason must allow or deny, not "%s"', $decision));

        new Reason(123, 'edit post', null, $decision, 'Undecided');
    }

    #[Test]
    public function it_checks_string_codes(): void
    {
        $reason = new Reason(123, 'edit post', null, 'allow', 'Allowed', 'role.allow');

        self::assertTrue($reason->is('role.allow'));
        self::assertFalse($reason->is('role.deny'));
        self::assertTrue($reason->isNot('role.deny'));
        self::assertFalse($reason->isNot('role.allow'));
    }

    #[Test]
    public function it_checks_enum_codes(): void
    {
        $reason = new Reason(123, 'mfa.disable.submit', null, 'deny', 'Mandatory', ReasonCode::Mandatory);

        self::assertTrue($reason->is(ReasonCode::Mandatory));
        self::assertFalse($reason->is(ReasonCode::Expired));
        self::assertTrue($reason->isNot(ReasonCode::Expired));
    }

    #[Test]
    public function it_does_not_match_an_enum_code_against_its_string_value(): void
    {
        $reason = new Reason(123, 'mfa.disable.submit', null, 'deny', 'Mandatory', ReasonCode::Mandatory);

        self::assertFalse($reason->is('mandatory'));
        self::assertTrue($reason->isNot('mandatory'));
    }

    #[Test]
    public function it_has_the_code_of_any_vote(): void
    {
        $roleVote = new Vote(IpAddressVoter::class, 'allow', 'Role allows it', 'role.allow');
        $noCodeVote = new Vote(TimeBasedVoter::class, 'abstain', 'Not mine');
        $mfaVote = new Vote(TimeBasedVoter::class, 'allow', 'Cleanup', ReasonCode::Expired);
        $reason = new Reason(123, 'test', null, 'allow', 'No deny votes found', 'role.allow', [$roleVote, $noCodeVote, $mfaVote]);

        self::assertTrue($reason->has('role.allow'));
        self::assertTrue($reason->has(ReasonCode::Expired));
        self::assertFalse($reason->has(ReasonCode::Mandatory));
        self::assertTrue($reason->hasNot(ReasonCode::Mandatory));
        self::assertFalse($reason->hasNot(ReasonCode::Expired));
        self::assertSame(['role.allow', ReasonCode::Expired], $reason->codes());
        self::assertSame([$roleVote, $noCodeVote, $mfaVote], $reason->votes);
    }

    #[Test]
    public function it_has_its_own_code_without_votes(): void
    {
        $reason = new Reason(123, 'test', null, 'deny', 'Mandatory', ReasonCode::Mandatory);

        self::assertTrue($reason->has(ReasonCode::Mandatory));
        self::assertSame([], $reason->codes());
    }

    #[Test]
    public function it_does_not_have_an_enum_code_by_its_string_value(): void
    {
        $reason = new Reason(123, 'test', null, 'allow', 'Allowed', votes: [
            new Vote(IpAddressVoter::class, 'allow', 'Cleanup', ReasonCode::Mandatory),
            new Vote(TimeBasedVoter::class, 'allow', 'Expired', 'expired'),
        ]);

        self::assertFalse($reason->has('mandatory'));
        self::assertTrue($reason->hasNot('mandatory'));
        self::assertFalse($reason->has(ReasonCode::Expired));
        self::assertTrue($reason->has('expired'));
    }

    #[Test]
    public function it_is_complete_by_default(): void
    {
        $reason = new Reason(123, 'test', null, 'allow', 'Allowed');

        self::assertTrue($reason->wasComplete());
        self::assertFalse($reason->wasNotComplete());
    }

    #[Test]
    public function it_knows_when_voting_stopped_early(): void
    {
        $reason = new Reason(123, 'test', null, 'deny', 'Denied', complete: false);

        self::assertFalse($reason->wasComplete());
        self::assertTrue($reason->wasNotComplete());
    }

    #[Test]
    public function it_knows_which_voter_decided(): void
    {
        $decidingVote = new Vote(IpAddressVoter::class, 'deny', 'MFA is mandatory');
        $reason = new Reason(123, 'test', null, 'deny', 'Deny wins - MFA is mandatory', votes: [$decidingVote], decidingVote: $decidingVote);

        self::assertSame($decidingVote, $reason->decidingVote);
        self::assertTrue($reason->wasDecidedBy(IpAddressVoter::class));
        self::assertFalse($reason->wasDecidedBy(TimeBasedVoter::class));
        self::assertFalse($reason->wasNotDecidedBy(IpAddressVoter::class));
        self::assertTrue($reason->wasNotDecidedBy(TimeBasedVoter::class));
        self::assertSame('MFA is mandatory', $reason->given());
    }

    #[Test]
    public function it_was_not_decided_by_anyone_without_a_deciding_vote(): void
    {
        $reason = new Reason(123, 'test', null, 'deny', 'All voters abstained');

        self::assertFalse($reason->wasDecidedBy(IpAddressVoter::class));
        self::assertTrue($reason->wasNotDecidedBy(IpAddressVoter::class));
        self::assertSame('All voters abstained', $reason->given());
    }

    #[Test]
    public function it_traces_every_vote_then_the_decision(): void
    {
        $reason = new Reason(123, 'mfa.disable.submit', null, 'deny', 'Deny wins - MFA is mandatory', ReasonCode::Mandatory, [
            new Vote(TimeBasedVoter::class, 'abstain', 'Not mine'),
            new Vote(IpAddressVoter::class, 'allow', 'Role allows it', 'role.allow'),
            new Vote(IpAddressVoter::class, 'deny', 'MFA is mandatory', ReasonCode::Mandatory),
        ]);

        self::assertSame(
            implode("\n", [
                '[abstain] RickRole\Voter\Example\TimeBasedVoter: Not mine',
                '[allow] RickRole\Voter\Example\IpAddressVoter: Role allows it (role.allow)',
                '[deny] RickRole\Voter\Example\IpAddressVoter: MFA is mandatory (RickRole\Tests\Unit\Fixture\ReasonCode::Mandatory)',
                '[deny] RickRole\Rick: Deny wins - MFA is mandatory (RickRole\Tests\Unit\Fixture\ReasonCode::Mandatory)',
            ]),
            $reason->trace()
        );
    }

    #[Test]
    public function it_converts_to_an_array(): void
    {
        $decidingVote = new Vote(IpAddressVoter::class, 'deny', 'MFA is mandatory', ReasonCode::Mandatory);
        $reason = new Reason(
            'user-1',
            'mfa.disable.submit',
            null,
            'deny',
            'Deny wins - MFA is mandatory',
            ReasonCode::Mandatory,
            [new Vote(TimeBasedVoter::class, 'allow', 'Role allows it'), $decidingVote],
            $decidingVote,
            false
        );

        self::assertSame([
            'user_id' => 'user-1',
            'permission' => 'mfa.disable.submit',
            'decision' => 'deny',
            'message' => 'Deny wins - MFA is mandatory',
            'code' => ReasonCode::Mandatory,
            'decided_by' => IpAddressVoter::class,
            'complete' => false,
            'votes' => [
                ['voter' => TimeBasedVoter::class, 'decision' => 'allow', 'message' => 'Role allows it', 'code' => null],
                ['voter' => IpAddressVoter::class, 'decision' => 'deny', 'message' => 'MFA is mandatory', 'code' => ReasonCode::Mandatory],
            ],
        ], $reason->toArray());
    }

    #[Test]
    public function it_converts_to_an_array_without_votes(): void
    {
        $reason = new Reason(123, 'edit post', null, 'deny', 'All voters abstained');

        self::assertNull($reason->toArray()['decided_by']);
        self::assertSame([], $reason->toArray()['votes']);
    }

    #[Test]
    public function it_converts_to_string_naming_the_deciding_voter(): void
    {
        $decidingVote = new Vote(IpAddressVoter::class, 'allow', 'Role allows it');
        $reason = new Reason(123, 'edit post', null, 'allow', 'No deny votes found - Role allows it', decidingVote: $decidingVote);

        self::assertSame(
            'Permission "edit post" for user "123" was allowed by RickRole\Voter\Example\IpAddressVoter: Role allows it',
            (string) $reason
        );
    }

    #[Test]
    public function it_converts_to_string_naming_rick_without_a_deciding_vote(): void
    {
        $reason = new Reason('user-1', 'edit post', null, 'deny', 'All voters abstained');

        self::assertSame(
            'Permission "edit post" for user "user-1" was denied by RickRole\Rick: All voters abstained',
            (string) $reason
        );
    }
}
