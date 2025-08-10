<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit\Strategy;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RickRole\Strategy\AllowWinsStrategy;
use RickRole\Voter\VoteResult;

/**
 * Unit tests for the AllowWinsStrategy class.
 */
final class AllowWinsStrategyTest extends TestCase
{
    private AllowWinsStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new AllowWinsStrategy();
    }

    #[Test]
    public function it_creates_successfully(): void
    {
        $strategy = new AllowWinsStrategy();
        self::assertInstanceOf(AllowWinsStrategy::class, $strategy);
    }

    #[Test]
    public function it_allows_when_single_allow_vote(): void
    {
        $votes = [VoteResult::allow('User is admin')];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isAllow());
        self::assertStringContainsString('Allow wins', $result->message());
    }

    #[Test]
    public function it_denies_when_single_deny_vote(): void
    {
        $votes = [VoteResult::deny('User not found')];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isDeny());
        self::assertStringContainsString('No allow votes', $result->message());
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
    public function it_allows_when_mixed_votes_with_allow(): void
    {
        $votes = [
            VoteResult::deny('First voter denies'),
            VoteResult::allow('Second voter allows'),
            VoteResult::abstain('Third voter abstains')
        ];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isAllow());
    }

    #[Test]
    public function it_denies_when_only_deny_and_abstain_votes(): void
    {
        $votes = [
            VoteResult::deny('First voter denies'),
            VoteResult::abstain('Second voter abstains'),
            VoteResult::deny('Third voter denies')
        ];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isDeny());
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
    public function it_prioritizes_first_allow_vote(): void
    {
        $votes = [
            VoteResult::deny('First deny'),
            VoteResult::allow('First allow'),
            VoteResult::allow('Second allow')
        ];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isAllow());
        self::assertStringContainsString('First allow', $result->message());
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
    public function it_handles_multiple_deny_votes(): void
    {
        $votes = [
            VoteResult::deny('No admin permission'),
            VoteResult::deny('No user permission'),
            VoteResult::deny('Account suspended')
        ];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isDeny());
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
        $winningReason = 'User has admin privileges';
        $votes = [
            VoteResult::deny('Some deny reason'),
            VoteResult::allow($winningReason)
        ];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isAllow());
        self::assertStringContainsString($winningReason, $result->message());
    }

    #[Test]
    public function it_handles_complex_voting_scenarios(): void
    {
        // Simulate complex real-world voting scenario
        $votes = [
            VoteResult::abstain('Role voter abstains - no roles defined'),
            VoteResult::deny('Permission voter denies - insufficient permissions'),
            VoteResult::allow('Admin voter allows - user is admin'),
            VoteResult::deny('Time voter denies - outside business hours')
        ];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isAllow());
        self::assertStringContainsString('Admin voter allows', $result->message());
    }

    #[Test]
    public function it_stops_early_when_allow_found(): void
    {
        // This test verifies early stopping optimization
        // Even though we can't directly test early stopping without modifying the strategy,
        // we can verify that the first allow vote is used
        $votes = [
            VoteResult::deny('First deny'),
            VoteResult::allow('First allow - should win'),
            VoteResult::allow('Second allow - should be ignored'),
            VoteResult::deny('Later deny - should be ignored')
        ];

        $result = $this->strategy->decide($votes);

        self::assertTrue($result->isAllow());
        self::assertStringContainsString('First allow - should win', $result->message());
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
            'single allow' => [
                'votes' => [VoteResult::allow('User authorized')],
                'expectedDecision' => 'allow',
                'description' => 'Single allow vote should result in allow decision'
            ],
            'single deny' => [
                'votes' => [VoteResult::deny('User unauthorized')],
                'expectedDecision' => 'deny',
                'description' => 'Single deny vote should result in deny decision'
            ],
            'single abstain' => [
                'votes' => [VoteResult::abstain('Cannot determine')],
                'expectedDecision' => 'abstain',
                'description' => 'Single abstain vote should result in abstain decision'
            ],
            'allow wins over deny' => [
                'votes' => [VoteResult::deny('First denies'), VoteResult::allow('Second allows')],
                'expectedDecision' => 'allow',
                'description' => 'Allow should win when mixed with deny votes'
            ],
            'allow wins over abstain' => [
                'votes' => [VoteResult::abstain('First abstains'), VoteResult::allow('Second allows')],
                'expectedDecision' => 'allow',
                'description' => 'Allow should win when mixed with abstain votes'
            ],
            'deny wins over abstain' => [
                'votes' => [VoteResult::abstain('First abstains'), VoteResult::deny('Second denies')],
                'expectedDecision' => 'deny',
                'description' => 'Deny should win when mixed with abstain votes only'
            ],
            'multiple allows' => [
                'votes' => [VoteResult::allow('First'), VoteResult::allow('Second'), VoteResult::allow('Third')],
                'expectedDecision' => 'allow',
                'description' => 'Multiple allows should result in allow'
            ],
            'multiple denies' => [
                'votes' => [VoteResult::deny('First'), VoteResult::deny('Second'), VoteResult::deny('Third')],
                'expectedDecision' => 'deny',
                'description' => 'Multiple denies should result in deny'
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
            'deny vote - continue' => [
                'voteResult' => VoteResult::deny('Denied'),
                'expected' => false,
                'description' => 'Should continue voting after deny vote'
            ],
            'abstain vote - continue' => [
                'voteResult' => VoteResult::abstain('Abstain'),
                'expected' => false,
                'description' => 'Should continue voting after abstain vote'
            ],
            'allow vote - stop' => [
                'voteResult' => VoteResult::allow('Allowed'),
                'expected' => true,
                'description' => 'Should stop voting after allow vote'
            ],
        ];
    }
}
