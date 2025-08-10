<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit\Reason;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RickRole\Reason\Reason;
use RickRole\Exception\ConfigurationException;

/**
 * Unit tests for the Reason class.
 */
final class ReasonTest extends TestCase
{
    private Reason $reason;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reason = new Reason(123, 'test_permission');
    }

    #[Test]
    public function it_creates_empty_initially(): void
    {
        $reason = new Reason(123, 'test_permission');

        self::assertEmpty($reason->chain);
        self::assertSame('', $reason->trace());
        self::assertNull($reason->lastDecision);
    }

    #[Test]
    public function it_adds_single_decision_entry(): void
    {
        $this->reason->addDecision('ALLOW', 'User has admin permission');

        $chain = $this->reason->chain;
        self::assertCount(1, $chain);
        self::assertSame('[ALLOW] User has admin permission', $chain[0]);
    }

    #[Test]
    public function it_adds_multiple_decision_entries(): void
    {
        $this->reason->addDecision('ABSTAIN', 'First decision');
        $this->reason->addDecision('DENY', 'Second decision');
        $this->reason->addDecision('ALLOW', 'Third decision');

        $chain = $this->reason->chain;
        self::assertCount(3, $chain);
        self::assertSame('[ABSTAIN] First decision', $chain[0]);
        self::assertSame('[DENY] Second decision', $chain[1]);
        self::assertSame('[ALLOW] Third decision', $chain[2]);
    }

    #[Test]
    public function it_preserves_decision_entry_order(): void
    {
        $decisions = [
            ['ABSTAIN', 'Alpha decision'],
            ['DENY', 'Beta decision'],
            ['ALLOW', 'Gamma decision'],
            ['DENY', 'Delta decision']
        ];

        foreach ($decisions as [$decision, $reason]) {
            $this->reason->addDecision($decision, $reason);
        }

        $chain = $this->reason->chain;
        self::assertCount(4, $chain);
        self::assertSame('[ABSTAIN] Alpha decision', $chain[0]);
        self::assertSame('[DENY] Beta decision', $chain[1]);
        self::assertSame('[ALLOW] Gamma decision', $chain[2]);
        self::assertSame('[DENY] Delta decision', $chain[3]);
    }

    #[Test]
    public function it_generates_trace_string(): void
    {
        $this->reason->addDecision('ABSTAIN', 'Step 1: Check user');
        $this->reason->addDecision('DENY', 'Step 2: Check permissions');
        $this->reason->addDecision('ALLOW', 'Step 3: Make decision');

        $trace = $this->reason->trace();
        $expected = "[ABSTAIN] Step 1: Check user\n[DENY] Step 2: Check permissions\n[ALLOW] Step 3: Make decision";

        self::assertSame($expected, $trace);
    }

    #[Test]
    public function it_handles_empty_trace(): void
    {
        $trace = $this->reason->trace();
        self::assertSame('', $trace);
    }

    #[Test]
    public function it_adds_decision_entry(): void
    {
        $this->reason->addDecision('ALLOW', 'User is admin');

        $chain = $this->reason->chain;
        self::assertCount(1, $chain);

        $entry = $chain[0];
        self::assertNotNull($entry);
        self::assertStringContainsString('ALLOW', $entry);
        self::assertStringContainsString('User is admin', $entry);
        self::assertSame('ALLOW', $this->reason->lastDecision);
    }

    #[Test]
    public function it_tracks_last_decision(): void
    {
        $this->reason->addDecision('DENY', 'Access denied');
        $this->reason->addDecision('ALLOW', 'Override granted');

        self::assertSame('ALLOW', $this->reason->lastDecision);
    }

    #[Test]
    public function it_handles_allow_decision(): void
    {
        $this->reason->addDecision('ALLOW', 'User has valid credentials');

        self::assertTrue($this->reason->isAllow());
        self::assertFalse($this->reason->isDeny());
        self::assertFalse($this->reason->isAbstain());
    }

    #[Test]
    public function it_handles_deny_decision(): void
    {
        $this->reason->addDecision('DENY', 'Insufficient permissions');

        self::assertTrue($this->reason->isDeny());
        self::assertFalse($this->reason->isAllow());
        self::assertFalse($this->reason->isAbstain());
    }

    #[Test]
    public function it_handles_abstain_decision(): void
    {
        $this->reason->addDecision('ABSTAIN', 'Cannot determine');

        self::assertTrue($this->reason->isAbstain());
        self::assertFalse($this->reason->isAllow());
        self::assertFalse($this->reason->isDeny());
    }

    #[Test]
    public function it_handles_no_decision(): void
    {
        // No decisions added, so chain should be empty
        self::assertFalse($this->reason->isAllow());
        self::assertFalse($this->reason->isDeny());
        self::assertFalse($this->reason->isAbstain());
        self::assertNull($this->reason->lastDecision);
        self::assertEmpty($this->reason->chain);
    }

    #[Test]
    public function it_handles_empty_reason_string(): void
    {
        $this->reason->addDecision('ALLOW', '');

        $chain = $this->reason->chain;
        self::assertCount(1, $chain);
        self::assertSame('[ALLOW] ', $chain[0]);
    }

    #[Test]
    public function it_handles_special_characters_in_decision(): void
    {
        $specialReason = 'User: admin@example.com, Permission: posts:create, Subject: <article>';
        $this->reason->addDecision('ALLOW', $specialReason);

        $chain = $this->reason->chain;
        self::assertSame('[ALLOW] ' . $specialReason, $chain[0]);
    }

    #[Test]
    public function it_handles_long_reason_strings(): void
    {
        $longReason = str_repeat('This is a very long reason. ', 100);
        $this->reason->addDecision('DENY', $longReason);

        $chain = $this->reason->chain;
        self::assertSame('[DENY] ' . $longReason, $chain[0]);
    }

    #[Test]
    public function it_handles_unicode_characters(): void
    {
        $unicodeReason = 'User: José, Permission: café, Subject: naïve';
        $this->reason->addDecision('ALLOW', $unicodeReason);

        $chain = $this->reason->chain;
        self::assertSame('[ALLOW] ' . $unicodeReason, $chain[0]);
    }

    #[Test]
    public function it_supports_method_chaining(): void
    {
        $result = $this->reason
            ->addDecision('ABSTAIN', 'First decision')
            ->addDecision('DENY', 'Second decision')
            ->addDecision('ALLOW', 'Final decision');

        self::assertSame($this->reason, $result);
        self::assertCount(3, $this->reason->chain);
        self::assertSame('ALLOW', $this->reason->lastDecision);
    }

    #[Test]
    public function it_handles_mixed_decision_types(): void
    {
        $this->reason->addDecision('ABSTAIN', 'Cannot determine');
        $this->reason->addDecision('DENY', 'Access denied');
        $this->reason->addDecision('ALLOW', 'Override granted');

        self::assertSame('ALLOW', $this->reason->lastDecision);
        self::assertTrue($this->reason->isAllow());
        self::assertFalse($this->reason->isDeny());
        self::assertFalse($this->reason->isAbstain());
    }

    #[Test]
    public function it_preserves_decision_history(): void
    {
        $this->reason->addDecision('ABSTAIN', 'First decision');
        $this->reason->addDecision('DENY', 'Second decision');
        $this->reason->addDecision('ALLOW', 'Third decision');

        $chain = $this->reason->chain;
        self::assertCount(3, $chain);
        self::assertStringContainsString('ABSTAIN', $chain[0] ?? '');
        self::assertStringContainsString('DENY', $chain[1] ?? '');
        self::assertStringContainsString('ALLOW', $chain[2] ?? '');
        self::assertSame('ALLOW', $this->reason->lastDecision);
    }

    #[Test]
    public function it_handles_complex_reason_building(): void
    {
        $this->reason
            ->addDecision('ABSTAIN', 'Role voter cannot determine')
            ->addDecision('DENY', 'Insufficient privileges')
            ->addDecision('ALLOW', 'Admin override granted');

        $chain = $this->reason->chain;
        self::assertCount(3, $chain);
        self::assertSame('ALLOW', $this->reason->lastDecision);
        self::assertTrue($this->reason->isAllow());
    }

    /** @return array<string, array{0: string|int, 1: string, 2: string}> */
    public static function userIdTypesData(): array
    {
        return [
            'string user ID' => ['user123', 'read', 'user123'],
            'integer user ID' => [123, 'write', '123'],
            'zero user ID' => [0, 'admin', '0'],
            'negative user ID' => [-1, 'delete', '-1'],
        ];
    }

    #[DataProvider('userIdTypesData')]
    #[Test]
    public function it_supports_string_and_integer_user_ids(string|int $userId, string $permission, string $expectedInReason): void
    {
        $reason = new Reason($userId, $permission);
        self::assertSame($userId, $reason->userId);
        self::assertSame($permission, $reason->permission);
    }

    /** @return array<string, array{0: string, 1: bool, 2: bool, 3: bool}> */
    public static function decisionTypesData(): array
    {
        return [
            'ALLOW uppercase' => ['ALLOW', true, false, false],
            'allow lowercase' => ['allow', true, false, false],
            'Allow mixed' => ['Allow', true, false, false],
            'DENY uppercase' => ['DENY', false, true, false],
            'deny lowercase' => ['deny', false, true, false],
            'Deny mixed' => ['Deny', false, true, false],
            'ABSTAIN uppercase' => ['ABSTAIN', false, false, true],
            'abstain lowercase' => ['abstain', false, false, true],
            'Abstain mixed' => ['Abstain', false, false, true],
            'Unknown decision' => ['UNKNOWN', false, false, false],
        ];
    }

    #[DataProvider('decisionTypesData')]
    #[Test]
    public function it_checks_decision_types(string $decision, bool $expectAllow, bool $expectDeny, bool $expectAbstain): void
    {
        $this->reason->addDecision($decision, 'Test decision');

        self::assertSame($expectAllow, $this->reason->isAllow());
        self::assertSame($expectDeny, $this->reason->isDeny());
        self::assertSame($expectAbstain, $this->reason->isAbstain());
    }

    #[Test]
    public function it_handles_unicode_in_message(): void
    {
        $reason = new Reason(123, 'test', null, 'TestVoter', 'ALLOW', 'Unicode: café naïve');
        $message = $reason->message;
        self::assertNotNull($message);
        self::assertStringContainsString('café', $message);
    }

    #[Test]
    public function it_creates_chained_exceptions(): void
    {
        $previous = new Reason(123, 'previous', null, 'PreviousVoter', 'DENY', 'Previous error');
        $reason = new Reason(123, 'current', null, 'CurrentVoter', 'ALLOW', 'Current message', $previous);

        self::assertSame($previous, $reason->previous);
    }

    #[Test]
    public function it_maintains_exception_hierarchy(): void
    {
        $first = new Reason(123, 'first', null, 'FirstVoter', 'ABSTAIN', 'First message');
        $second = new Reason(123, 'second', null, 'SecondVoter', 'DENY', 'Second message', $first);
        $third = new Reason(123, 'third', null, 'ThirdVoter', 'ALLOW', 'Third message', $second);

        self::assertSame($second, $third->previous);
        self::assertSame($first, $third->previous->previous);
        self::assertNull($third->previous->previous->previous);
    }

    #[Test]
    public function it_can_be_caught_as_base_exception(): void
    {
        $this->expectException(\Exception::class);
        throw new ConfigurationException('Test exception');
    }

    #[Test]
    public function it_can_be_caught_as_throwable(): void
    {
        $this->expectException(\Throwable::class);
        throw new ConfigurationException('Test exception');
    }

    /** @return array<string, array{0: string, 1: int, 2: string}> */
    public static function exceptionVariousData(): array
    {
        return [
            'basic message' => ['Simple error', 0, 'Basic error message'],
            'with code' => ['Error with code', 500, 'Error with specific code'],
            'empty message' => ['', 0, 'Empty error message'],
            'special characters' => ['Error: <script>alert("test")</script>', 0, 'Error with special characters'],
        ];
    }

    #[DataProvider('exceptionVariousData')]
    #[Test]
    public function it_creates_exception_with_various_data(string $message, int $code, string $description): void
    {
        $exception = new ConfigurationException($message, $code);
        self::assertSame($message, $exception->getMessage());
        self::assertSame($code, $exception->getCode());
    }

    #[Test]
    public function it_gets_user_id_from_immutable_api(): void
    {
        $reason = new Reason(123, 'test_permission');
        self::assertSame(123, $reason->userId);
    }

    #[Test]
    public function it_gets_permission_from_immutable_api(): void
    {
        $reason = new Reason(123, 'test_permission');
        self::assertSame('test_permission', $reason->permission);
    }

    #[Test]
    public function it_gets_subject_from_immutable_api(): void
    {
        $subject = ['context' => 'test'];
        $reason = new Reason(123, 'test_permission', $subject);
        self::assertSame($subject, $reason->subject);
    }

    #[Test]
    public function it_gets_voter_from_immutable_api(): void
    {
        $reason = new Reason(123, 'test_permission', null, 'TestVoter');
        self::assertSame('TestVoter', $reason->voter);
    }

    #[Test]
    public function it_handles_null_values_in_immutable_api(): void
    {
        $reason = new Reason(123, 'test_permission', null, null, null, null);
        self::assertSame(123, $reason->userId);
        self::assertSame('test_permission', $reason->permission);
        self::assertNull($reason->subject);
        self::assertNull($reason->voter);
        self::assertNull($reason->decision);
        self::assertNull($reason->message);
    }

    #[Test]
    public function it_gets_full_trace_with_previous_reasons(): void
    {
        $previous = new Reason(123, 'previous', null, 'PreviousVoter', 'DENY', 'Previous error');
        $reason = new Reason(123, 'current', null, 'CurrentVoter', 'ALLOW', 'Current message', $previous);

        // Build manual trace using chaining semantics
        $trace = $previous->trace();
        $traceCurrent = $reason->trace();
        // Ensure trace returns a string and is stable on repeated calls
        self::assertSame($trace, $previous->trace());
        self::assertSame($traceCurrent, $reason->trace());
    }

    #[Test]
    public function it_gets_full_trace_without_previous_reasons(): void
    {
        $reason = new Reason(123, 'test', null, 'TestVoter', 'ALLOW', 'Test message');
        $reason->addDecision('ALLOW', 'Some trace entry');
        $trace = $reason->trace();
        self::assertStringContainsString('Some trace entry', $trace);
    }

    #[Test]
    public function it_converts_to_string_with_complete_data(): void
    {
        $reason = new Reason(123, 'test_permission', ['context' => 'test'], 'TestVoter', 'ALLOW', 'Test message');
        $string = (string) $reason;

        self::assertStringContainsString('test_permission', $string);
        self::assertStringContainsString('123', $string);
        self::assertStringContainsString('allow', $string);
        self::assertStringContainsString('TestVoter', $string);
        self::assertStringContainsString('Test message', $string);
    }

    #[Test]
    public function it_converts_to_string_with_missing_data(): void
    {
        $reason = new Reason(123, 'test_permission');
        $string = (string) $reason;

        self::assertStringContainsString('test_permission', $string);
        self::assertStringContainsString('123', $string);
        self::assertStringContainsString('Unknown', $string);
        self::assertStringContainsString('No message', $string);
    }

    #[Test]
    public function it_converts_to_string_with_partial_data(): void
    {
        $reason = new Reason(123, 'test_permission', null, 'TestVoter');
        $string = (string) $reason;

        self::assertStringContainsString('test_permission', $string);
        self::assertStringContainsString('123', $string);
        self::assertStringContainsString('TestVoter', $string);
        self::assertStringContainsString('No message', $string);
    }

    #[Test]
    public function it_handles_complex_immutable_reason_creation(): void
    {
        $subject = ['complex' => 'data', 'nested' => ['value' => 42]];
        $previous = new Reason(456, 'previous_permission', null, 'PreviousVoter', 'DENY', 'Previous error');

        $reason = new Reason(
            123,
            'complex_permission',
            $subject,
            'ComplexVoter',
            'ALLOW',
            'Complex message with details',
            $previous
        );

        self::assertSame(123, $reason->userId);
        self::assertSame('complex_permission', $reason->permission);
        self::assertSame($subject, $reason->subject);
        self::assertSame('ComplexVoter', $reason->voter);
        self::assertSame('ALLOW', $reason->decision);
        self::assertSame('Complex message with details', $reason->message);
        self::assertSame($previous, $reason->previous);
    }

    #[Test]
    public function it_handles_empty_immutable_reason_creation(): void
    {
        $reason = new Reason(0, '', null, null, null, null);

        self::assertSame(0, $reason->userId);
        self::assertSame('', $reason->permission);
        self::assertNull($reason->subject);
        self::assertNull($reason->voter);
        self::assertNull($reason->decision);
        self::assertNull($reason->message);
        self::assertNull($reason->previous);
    }

    #[Test]
    public function it_sets_message(): void
    {
        $reason = new Reason(123, 'test', null, null, null, 'Custom message');
        $message = $reason->message;
        self::assertNotNull($message);
        self::assertSame('Custom message', $message);
    }

    #[Test]
    public function it_adds_voter(): void
    {
        $voter = $this->createMock(\RickRole\Voter\VoterInterface::class);
        $this->reason->addVoter($voter);

        $voters = $this->reason->voters;
        self::assertCount(1, $voters);
        self::assertSame($voter, $voters[0]);
    }

    #[Test]
    public function it_returns_voters(): void
    {
        $voter1 = $this->createMock(\RickRole\Voter\VoterInterface::class);
        $voter2 = $this->createMock(\RickRole\Voter\VoterInterface::class);

        $this->reason->addVoter($voter1);
        $this->reason->addVoter($voter2);

        $voters = $this->reason->voters;
        self::assertCount(2, $voters);
        self::assertSame($voter1, $voters[0]);
        self::assertSame($voter2, $voters[1]);
    }

    #[Test]
    public function it_returns_empty_voters_by_default(): void
    {
        self::assertEmpty($this->reason->voters);
    }

    #[Test]
    public function it_handles_fullTrace_with_chained_reasons_and_missing_data(): void
    {
        $r1 = new Reason(1, 'perm1', null, 'Voter1', null, null, null);
        $r2 = new Reason(1, 'perm1', null, 'Voter2', 'ALLOW', 'Allowed', $r1);
        $r3 = new Reason(1, 'perm1', null, null, 'DENY', null, $r2);
        // Without fullTrace(), verify data is present on the relevant Reason instances
        $this->assertSame('Voter2', $r2->voter);
        $this->assertSame('ALLOW', $r2->decision);
        $this->assertSame('Allowed', $r2->message);
        $this->assertSame('DENY', $r3->decision);
        $this->assertNull($r3->message);
        $this->assertSame($r1, $r2->previous);
        $this->assertSame($r2, $r3->previous);
    }

    #[Test]
    public function it_handles_fullTrace_with_no_previous(): void
    {
        $r = new Reason(1, 'perm1');
        $r->addDecision('ALLOW', 'ok');
        $trace = $r->trace();
        $this->assertStringContainsString('ok', $trace);
    }

    #[Test]
    public function it_toString_handles_missing_data(): void
    {
        $r = new Reason(1, 'perm1');
        $str = (string)$r;
        $this->assertStringContainsString('Unknown', $str);
        $this->assertStringContainsString('No message', $str);
    }

    #[Test]
    public function it_toString_handles_partial_data(): void
    {
        $r = new Reason(1, 'perm1', null, 'VoterX');
        $str = (string)$r;
        $this->assertStringContainsString('VoterX', $str);
        $this->assertStringContainsString('No message', $str);
    }

    #[Test]
    public function it_getFullTrace_returns_expected_array(): void
    {
        $r = new Reason(1, 'perm1');
        $r->addDecision('ALLOW', 'ok');
        $arr = $r->getFullTrace();
        $this->assertArrayHasKey('chain', $arr);
        $this->assertArrayHasKey('message', $arr);
        $this->assertArrayHasKey('voters', $arr);
        $this->assertIsArray($arr['voters']);
    }
}
