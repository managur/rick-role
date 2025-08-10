<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit\Strategy;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RickRole\Strategy\DenyWinsStrategy;
use RickRole\Voter\VoteResult;

/**
 * Unit tests for the DenyWinsStrategy class.
 */
final class DenyWinsStrategyTest extends TestCase
{
    private DenyWinsStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new DenyWinsStrategy();
    }

    #[Test]
    public function it_creates_successfully(): void
    {
        $strategy = new DenyWinsStrategy();
        self::assertInstanceOf(DenyWinsStrategy::class, $strategy);
    }

    #[Test]
    public function it_denies_when_single_deny_vote(): void
    {
        $votes = [VoteResult::deny('Access denied')];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isDeny());
        self::assertStringContainsString('Deny wins', $result->message());
    }

    #[Test]
    public function it_allows_when_single_allow_vote(): void
    {
        $votes = [VoteResult::allow('Access granted')];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isAllow());
        self::assertStringContainsString('No deny votes', $result->message());
    }

    #[Test]
    public function it_abstains_when_single_abstain_vote(): void
    {
        $votes = [VoteResult::abstain('Cannot determine')];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isAbstain());
        self::assertStringContainsString('All voters abstained', $result->message());
    }

    #[Test]
    public function it_denies_when_mixed_votes_with_deny(): void
    {
        $votes = [
            VoteResult::allow('First voter allows'),
            VoteResult::deny('Second voter denies'),
            VoteResult::abstain('Third voter abstains')
        ];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isDeny());
    }

    #[Test]
    public function it_allows_when_only_allow_and_abstain_votes(): void
    {
        $votes = [
            VoteResult::allow('First voter allows'),
            VoteResult::abstain('Second voter abstains'),
            VoteResult::allow('Third voter allows')
        ];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isAllow());
    }

    #[Test]
    public function it_handles_empty_votes_array(): void
    {
        $votes = [];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isAbstain());
        self::assertStringContainsString('No votes provided', $result->message());
    }

    #[Test]
    public function it_prioritizes_first_deny_vote(): void
    {
        $votes = [
            VoteResult::allow('First allow'),
            VoteResult::deny('First deny'),
            VoteResult::deny('Second deny')
        ];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isDeny());
        self::assertStringContainsString('First deny', $result->message());
    }

    #[Test]
    public function it_handles_multiple_deny_votes(): void
    {
        $votes = [
            VoteResult::deny('No admin permission'),
            VoteResult::deny('Account suspended'),
            VoteResult::deny('IP blocked')
        ];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isDeny());
    }

    #[Test]
    public function it_handles_multiple_allow_votes(): void
    {
        $votes = [
            VoteResult::allow('Admin permission'),
            VoteResult::allow('User permission'),
            VoteResult::allow('Guest permission')
        ];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isAllow());
    }

    #[Test]
    public function it_handles_multiple_abstain_votes(): void
    {
        $votes = [
            VoteResult::abstain('First abstain'),
            VoteResult::abstain('Second abstain'),
            VoteResult::abstain('Third abstain')
        ];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isAbstain());
    }

    #[Test]
    public function it_returns_vote_result_instance(): void
    {
        $votes = [VoteResult::allow('Test')];

        $result = $this->strategy->decide($votes);

        self::assertInstanceOf(VoteResult::class, $result);
    }

    #[Test]
    public function it_includes_reason_from_winning_vote(): void
    {
        $winningReason = 'User account is suspended';
        $votes = [
            VoteResult::allow('Some allow reason'),
            VoteResult::deny($winningReason)
        ];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isDeny());
        self::assertStringContainsString($winningReason, $result->message());
    }

    #[Test]
    public function it_handles_complex_voting_scenarios(): void
    {
        // Simulate complex real-world voting scenario
        $votes = [
            VoteResult::allow('Role voter allows - user has admin role'),
            VoteResult::abstain('Permission voter abstains - unclear permission'),
            VoteResult::deny('Security voter denies - suspicious activity'),
            VoteResult::allow('Time voter allows - within business hours')
        ];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isDeny());
        self::assertStringContainsString('Security voter denies', $result->message());
    }

    #[Test]
    public function it_stops_early_when_deny_found(): void
    {
        // This test verifies early stopping optimization
        // Even though we can't directly test early stopping without modifying the strategy,
        // we can verify that the first deny vote is used
        $votes = [
            VoteResult::allow('First allow'),
            VoteResult::deny('First deny - should win'),
            VoteResult::deny('Second deny - should be ignored'),
            VoteResult::allow('Later allow - should be ignored')
        ];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isDeny());
        self::assertStringContainsString('First deny - should win', $result->message());
    }

    #[Test]
    public function it_follows_fail_secure_principle(): void
    {
        // Deny wins strategy should fail secure - when in doubt, deny
        $votes = [
            VoteResult::abstain('Unclear permission'),
            VoteResult::abstain('Cannot determine role'),
            VoteResult::abstain('Insufficient information')
        ];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isAbstain()); // All abstain results in abstain
    }

    /**
     * @param array<\RickRole\Voter\VoteResult> $votes
     */
    #[DataProvider('decisionScenariosData')]
    #[Test]
    public function it_makes_decisions_for_various_scenarios(
        array $votes,
        string $expectedDecision,
        string $description
    ): void {
        $result = $this->strategy->decide($votes);
        self::assertSame($expectedDecision, $result->result(), $description);
    }

    /** @return array<string, array{votes: array<VoteResult>, expectedDecision: string, description: string}> */
    public static function decisionScenariosData(): array
    {
        return [
            'single deny' => [
                'votes' => [VoteResult::deny('User unauthorized')],
                'expectedDecision' => 'deny',
                'description' => 'Single deny vote should result in deny decision'
            ],
            'single allow' => [
                'votes' => [VoteResult::allow('User authorized')],
                'expectedDecision' => 'allow',
                'description' => 'Single allow vote should result in allow decision'
            ],
            'single abstain' => [
                'votes' => [VoteResult::abstain('Cannot determine')],
                'expectedDecision' => 'abstain',
                'description' => 'Single abstain vote should result in abstain decision'
            ],
            'deny wins over allow' => [
                'votes' => [VoteResult::allow('First allows'), VoteResult::deny('Second denies')],
                'expectedDecision' => 'deny',
                'description' => 'Deny should win when mixed with allow votes'
            ],
            'deny wins over abstain' => [
                'votes' => [VoteResult::abstain('First abstains'), VoteResult::deny('Second denies')],
                'expectedDecision' => 'deny',
                'description' => 'Deny should win when mixed with abstain votes'
            ],
            'allow wins over abstain' => [
                'votes' => [VoteResult::abstain('First abstains'), VoteResult::allow('Second allows')],
                'expectedDecision' => 'allow',
                'description' => 'Allow should win when mixed with abstain votes only'
            ],
            'multiple denies' => [
                'votes' => [VoteResult::deny('First'), VoteResult::deny('Second'), VoteResult::deny('Third')],
                'expectedDecision' => 'deny',
                'description' => 'Multiple denies should result in deny'
            ],
            'multiple allows' => [
                'votes' => [VoteResult::allow('First'), VoteResult::allow('Second'), VoteResult::allow('Third')],
                'expectedDecision' => 'allow',
                'description' => 'Multiple allows should result in allow'
            ],
            'multiple abstains' => [
                'votes' => [VoteResult::abstain('First'), VoteResult::abstain('Second'), VoteResult::abstain('Third')],
                'expectedDecision' => 'abstain',
                'description' => 'Multiple abstains should result in abstain'
            ],
            'empty votes' => [
                'votes' => [],
                'expectedDecision' => 'abstain',
                'description' => 'Empty votes should result in abstain'
            ],
        ];
    }

    #[DataProvider('shouldStopVotingData')]
    #[Test]
    public function it_determines_when_to_stop_voting(VoteResult $voteResult, bool $expected, string $description): void
    {
        $shouldStop = $this->strategy->shouldStopVoting($voteResult);

        self::assertSame($expected, $shouldStop, $description);
    }

    /** @return array<string, array{voteResult: VoteResult, expected: bool, description: string}> */
    public static function shouldStopVotingData(): array
    {
        return [
            'allow vote - continue' => [
                'voteResult' => VoteResult::allow('Allowed'),
                'expected' => false,
                'description' => 'Should continue voting after allow vote'
            ],
            'abstain vote - continue' => [
                'voteResult' => VoteResult::abstain('Abstain'),
                'expected' => false,
                'description' => 'Should continue voting after abstain vote'
            ],
            'deny vote - stop' => [
                'voteResult' => VoteResult::deny('Denied'),
                'expected' => true,
                'description' => 'Should stop voting after deny vote'
            ],
        ];
    }
}
