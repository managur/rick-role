<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit\Voter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RickRole\Voter\VoteResult;
use RickRole\Voter\VoterInterface;

/**
 * Unit tests for the VoteResult class.
 */
final class VoteResultTest extends TestCase
{
    #[Test]
    public function it_creates_allow_result(): void
    {
        $result = VoteResult::allow('Valid permission');

        self::assertTrue($result->isAllow());
        self::assertFalse($result->isDeny());
        self::assertFalse($result->isAbstain());
        self::assertSame('Valid permission', $result->message());
    }

    #[Test]
    public function it_creates_deny_result(): void
    {
        $result = VoteResult::deny('Invalid permission');

        self::assertTrue($result->isDeny());
        self::assertFalse($result->isAllow());
        self::assertFalse($result->isAbstain());
        self::assertSame('Invalid permission', $result->message());
    }

    #[Test]
    public function it_creates_abstain_result(): void
    {
        $result = VoteResult::abstain('Cannot determine');

        self::assertTrue($result->isAbstain());
        self::assertFalse($result->isAllow());
        self::assertFalse($result->isDeny());
        self::assertSame('Cannot determine', $result->message());
    }

    #[Test]
    public function it_handles_empty_reason(): void
    {
        $result = VoteResult::allow('');

        self::assertTrue($result->isAllow());
        self::assertSame('', $result->message());
    }

    #[Test]
    public function it_handles_null_reason(): void
    {
        $result = VoteResult::deny(null);

        self::assertTrue($result->isDeny());
        self::assertSame('Access denied', $result->message());
    }

    #[Test]
    public function it_handles_numeric_reason(): void
    {
        $result = VoteResult::abstain('123');

        self::assertTrue($result->isAbstain());
        self::assertSame('123', $result->message());
    }

    #[Test]
    public function it_handles_special_characters_in_reason(): void
    {
        $reason = 'Special chars: @#$%^&*()_+{}|:<>?';
        $result = VoteResult::allow($reason);

        self::assertTrue($result->isAllow());
        self::assertSame($reason, $result->message());
    }

    #[Test]
    public function it_handles_long_reason(): void
    {
        $longReason = str_repeat('Long reason text ', 100);
        $result = VoteResult::deny($longReason);

        self::assertTrue($result->isDeny());
        self::assertSame($longReason, $result->message());
    }

    #[Test]
    public function it_handles_unicode_reason(): void
    {
        $unicodeReason = 'Unicode: 你好 🌍 émojis';
        $result = VoteResult::abstain($unicodeReason);

        self::assertTrue($result->isAbstain());
        self::assertSame($unicodeReason, $result->message());
    }

    #[Test]
    public function it_maintains_decision_consistency(): void
    {
        $allowResult = VoteResult::allow('test');
        self::assertTrue($allowResult->isAllow());
        self::assertFalse($allowResult->isDeny());
        self::assertFalse($allowResult->isAbstain());

        $denyResult = VoteResult::deny('test');
        self::assertTrue($denyResult->isDeny());
        self::assertFalse($denyResult->isAllow());
        self::assertFalse($denyResult->isAbstain());

        $abstainResult = VoteResult::abstain('test');
        self::assertTrue($abstainResult->isAbstain());
        self::assertFalse($abstainResult->isAllow());
        self::assertFalse($abstainResult->isDeny());
    }

    #[Test]
    public function it_converts_to_string(): void
    {
        $allowResult = VoteResult::allow('Success');
        self::assertSame('ALLOW: Success', (string) $allowResult);

        $denyResult = VoteResult::deny('Failed');
        self::assertSame('DENY: Failed', (string) $denyResult);

        $abstainResult = VoteResult::abstain('Unknown');
        self::assertSame('ABSTAIN: Unknown', (string) $abstainResult);
    }

    #[Test]
    public function it_converts_to_string_with_empty_reason(): void
    {
        $result = VoteResult::allow('');
        self::assertSame('ALLOW: ', (string) $result);
    }

    #[Test]
    public function it_converts_to_string_with_null_reason(): void
    {
        $result = VoteResult::deny(null);
        self::assertSame('DENY: Access denied', (string) $result);
    }

    #[Test]
    public function it_creates_independent_instances(): void
    {
        $result1 = VoteResult::allow('First');
        $result2 = VoteResult::allow('Second');

        self::assertNotSame($result1, $result2);
        self::assertSame('First', $result1->message());
        self::assertSame('Second', $result2->message());
    }

    #[Test]
    public function it_preserves_reason_type(): void
    {
        $stringReason = 'String reason';
        $result = VoteResult::allow($stringReason);
        self::assertSame($stringReason, $result->message());
        self::assertIsString($result->message());
    }

    #[Test]
    public function it_handles_multiple_factory_calls(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $result = VoteResult::allow("Test $i");
            self::assertTrue($result->isAllow());
            self::assertSame("Test $i", $result->message());
        }
    }

    #[DataProvider('voteResultCreationData')]
    #[Test]
    public function it_creates_vote_results_with_various_data(string $method, string $reason, bool $expectedAllow, bool $expectedDeny, bool $expectedAbstain): void
    {
        /** @var VoteResult $result */
        $result = VoteResult::$method($reason);

        self::assertSame($expectedAllow, $result->isAllow());
        self::assertSame($expectedDeny, $result->isDeny());
        self::assertSame($expectedAbstain, $result->isAbstain());
        self::assertSame($reason, $result->message());
    }

    /** @return array<string, array{method: string, reason: string, expectedAllow: bool, expectedDeny: bool, expectedAbstain: bool}> */
    public static function voteResultCreationData(): array
    {
        return [
            'allow with reason' => [
                'method' => 'allow',
                'reason' => 'Allowed',
                'expectedAllow' => true,
                'expectedDeny' => false,
                'expectedAbstain' => false
            ],
            'deny with reason' => [
                'method' => 'deny',
                'reason' => 'Denied',
                'expectedAllow' => false,
                'expectedDeny' => true,
                'expectedAbstain' => false
            ],
            'abstain with reason' => [
                'method' => 'abstain',
                'reason' => 'Abstained',
                'expectedAllow' => false,
                'expectedDeny' => false,
                'expectedAbstain' => true
            ],
            'allow empty' => [
                'method' => 'allow',
                'reason' => '',
                'expectedAllow' => true,
                'expectedDeny' => false,
                'expectedAbstain' => false
            ],
            'deny empty' => [
                'method' => 'deny',
                'reason' => '',
                'expectedAllow' => false,
                'expectedDeny' => true,
                'expectedAbstain' => false
            ],
            'abstain empty' => [
                'method' => 'abstain',
                'reason' => '',
                'expectedAllow' => false,
                'expectedDeny' => false,
                'expectedAbstain' => true
            ],
            'allow numeric' => [
                'method' => 'allow',
                'reason' => '123',
                'expectedAllow' => true,
                'expectedDeny' => false,
                'expectedAbstain' => false
            ],
            'deny numeric' => [
                'method' => 'deny',
                'reason' => '456',
                'expectedAllow' => false,
                'expectedDeny' => true,
                'expectedAbstain' => false
            ],
            'abstain numeric' => [
                'method' => 'abstain',
                'reason' => '789',
                'expectedAllow' => false,
                'expectedDeny' => false,
                'expectedAbstain' => true
            ],
        ];
    }

    #[Test]
    public function it_returns_result_value(): void
    {
        $allowResult = VoteResult::allow('test');
        self::assertSame('allow', $allowResult->result());

        $denyResult = VoteResult::deny('test');
        self::assertSame('deny', $denyResult->result());

        $abstainResult = VoteResult::abstain('test');
        self::assertSame('abstain', $abstainResult->result());
    }

    #[Test]
    public function it_returns_voter_when_provided(): void
    {
        $mockVoter = $this->createMock(VoterInterface::class);

        $result = VoteResult::allow('test', $mockVoter);
        self::assertSame($mockVoter, $result->voter());
    }

    #[Test]
    public function it_returns_null_voter_when_not_provided(): void
    {
        $result = VoteResult::allow('test');
        self::assertNull($result->voter());
    }

    #[Test]
    public function it_returns_null_voter_for_deny_without_voter(): void
    {
        $result = VoteResult::deny('test');
        self::assertNull($result->voter());
    }

    #[Test]
    public function it_returns_null_voter_for_abstain_without_voter(): void
    {
        $result = VoteResult::abstain('test');
        self::assertNull($result->voter());
    }
}
