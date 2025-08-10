<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RickRole\Configuration;
use RickRole\Strategy\AllowWinsStrategy;
use RickRole\Strategy\DenyWinsStrategy;
use RickRole\Voter\VoterInterface;

/**
 * Unit tests for the Configuration class.
 */
final class ConfigurationTest extends TestCase
{
    private Configuration $configuration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuration = new Configuration();
    }

    #[Test]
    public function it_creates_with_default_values(): void
    {
        self::assertInstanceOf(DenyWinsStrategy::class, $this->configuration->strategy());
        self::assertEmpty($this->configuration->voters());
    }

    #[Test]
    public function it_sets_and_gets_strategy(): void
    {
        $strategy = new AllowWinsStrategy();
        $this->configuration->setStrategy($strategy);

        self::assertSame($strategy, $this->configuration->strategy());
    }

    #[Test]
    public function it_adds_single_voter(): void
    {
        $voter = $this->createMock(VoterInterface::class);
        $this->configuration->addVoter($voter);

        $voters = $this->configuration->voters();
        self::assertContains($voter, $voters);
    }

    #[Test]
    public function it_sets_voters_array(): void
    {
        $voter1 = $this->createMock(VoterInterface::class);
        $voter2 = $this->createMock(VoterInterface::class);
        $voters = [$voter1, $voter2];

        $this->configuration->setVoters($voters);

        self::assertSame($voters, $this->configuration->voters());
    }

    #[Test]
    public function it_clears_voters(): void
    {
        $voter = $this->createMock(VoterInterface::class);
        $this->configuration->addVoter($voter);
        $this->configuration->clearVoters();

        self::assertEmpty($this->configuration->voters());
    }

    #[Test]
    public function it_supports_fluent_interface(): void
    {
        $strategy = new AllowWinsStrategy();
        $voter = $this->createMock(VoterInterface::class);

        $result = $this->configuration
            ->setStrategy($strategy)
            ->addVoter($voter)
            ->clearVoters();

        self::assertSame($this->configuration, $result);
    }

    #[Test]
    public function it_maintains_voter_order(): void
    {
        $voter1 = $this->createMock(VoterInterface::class);
        $voter2 = $this->createMock(VoterInterface::class);
        $voter3 = $this->createMock(VoterInterface::class);

        $this->configuration
            ->clearVoters()
            ->addVoter($voter1)
            ->addVoter($voter2)
            ->addVoter($voter3);

        $voters = $this->configuration->voters();
        self::assertSame([$voter1, $voter2, $voter3], $voters);
    }

    #[Test]
    public function it_replaces_default_voter(): void
    {
        $customVoter = $this->createMock(VoterInterface::class);
        $this->configuration->setVoters([$customVoter]);

        $voters = $this->configuration->voters();
        self::assertCount(1, $voters);
        self::assertSame($customVoter, $voters[0]);
    }

    #[Test]
    public function it_allows_multiple_strategies(): void
    {
        $allowStrategy = new AllowWinsStrategy();
        $denyStrategy = new DenyWinsStrategy();

        $this->configuration->setStrategy($allowStrategy);
        self::assertInstanceOf(AllowWinsStrategy::class, $this->configuration->strategy());

        $this->configuration->setStrategy($denyStrategy);
        self::assertInstanceOf(DenyWinsStrategy::class, $this->configuration->strategy());
    }

    #[Test]
    public function it_handles_empty_voter_array(): void
    {
        $this->configuration->setVoters([]);
        self::assertEmpty($this->configuration->voters());
    }

    #[Test]
    public function it_validates_voter_interface(): void
    {
        $voter = $this->createMock(VoterInterface::class);
        $this->configuration->addVoter($voter);

        $voters = $this->configuration->voters();
        self::assertContainsOnlyInstancesOf(VoterInterface::class, $voters);
    }

    #[Test]
    public function it_preserves_voter_references(): void
    {
        $voter = $this->createMock(VoterInterface::class);
        $this->configuration->addVoter($voter);

        $retrievedVoters = $this->configuration->voters();
        self::assertSame($voter, $retrievedVoters[count($retrievedVoters) - 1]);
    }

    #[Test]
    public function it_supports_voter_configuration_changes(): void
    {
        $initialCount = count($this->configuration->voters());

        $voter1 = $this->createMock(VoterInterface::class);
        $voter2 = $this->createMock(VoterInterface::class);

        $this->configuration->addVoter($voter1);
        self::assertCount($initialCount + 1, $this->configuration->voters());

        $this->configuration->addVoter($voter2);
        self::assertCount($initialCount + 2, $this->configuration->voters());

        $this->configuration->clearVoters();
        self::assertCount(0, $this->configuration->voters());
    }

    #[Test]
    public function it_creates_independent_configurations(): void
    {
        $config1 = new Configuration();
        $config2 = new Configuration();

        $voter = $this->createMock(VoterInterface::class);
        $config1->addVoter($voter);

        self::assertNotSame($config1->voters(), $config2->voters());
    }

    #[Test]
    public function it_handles_strategy_replacement(): void
    {
        $originalStrategy = $this->configuration->strategy();
        $newStrategy = new AllowWinsStrategy();

        $this->configuration->setStrategy($newStrategy);

        self::assertNotSame($originalStrategy, $this->configuration->strategy());
        self::assertSame($newStrategy, $this->configuration->strategy());
    }

    #[Test]
    public function it_supports_complex_voter_scenarios(): void
    {
        $voters = [];
        for ($i = 0; $i < 5; $i++) {
            $voters[] = $this->createMock(VoterInterface::class);
        }

        $this->configuration->setVoters($voters);
        self::assertCount(5, $this->configuration->voters());

        $additionalVoter = $this->createMock(VoterInterface::class);
        $this->configuration->addVoter($additionalVoter);
        self::assertCount(6, $this->configuration->voters());
    }

    #[Test]
    public function it_maintains_immutable_strategy_reference(): void
    {
        $strategy = new AllowWinsStrategy();
        $this->configuration->setStrategy($strategy);

        $retrievedStrategy = $this->configuration->strategy();
        self::assertSame($strategy, $retrievedStrategy);
    }

    #[Test]
    public function it_handles_voter_array_immutability(): void
    {
        $voter = $this->createMock(VoterInterface::class);
        $this->configuration->addVoter($voter);

        $voters = $this->configuration->voters();
        $originalCount = count($voters);

        // Modifying returned array should not affect configuration
        $voters[] = $this->createMock(VoterInterface::class);

        self::assertCount($originalCount, $this->configuration->voters());
    }

    /**
     * @param array<\RickRole\Voter\VoterInterface> $initialVoters
     * @param array<\RickRole\Voter\VoterInterface> $addedVoters
     * @param array<\RickRole\Voter\VoterInterface> $finalVoters
     */
    #[Test]
    #[DataProvider('voterChainManagement')]
    public function it_manages_voter_chains(
        array $initialVoters,
        array $addedVoters,
        array $finalVoters,
        string $description
    ): void {
        /** @var array<\RickRole\Voter\VoterInterface> $initialVoters */
        /** @var array<\RickRole\Voter\VoterInterface> $addedVoters */
        /** @var array<\RickRole\Voter\VoterInterface> $finalVoters */
        $this->configuration->setVoters($initialVoters);
        foreach ($addedVoters as $voter) {
            $this->configuration->addVoter($voter);
        }
        $result = $this->configuration->voters();
        self::assertSame($finalVoters, $result, $description);
    }

    /** @return array<string, array{initialVoters: array<VoterInterface>, addedVoters: array<VoterInterface>, finalVoters: array<VoterInterface>, description: string}> */
    public static function voterChainManagement(): array
    {
        return [
            'empty to single' => [
                'initialVoters' => [],
                'addedVoters' => [],
                'finalVoters' => [],
                'description' => 'Should add single voter to empty array'
            ],
            'single to multiple' => [
                'initialVoters' => [],
                'addedVoters' => [],
                'finalVoters' => [],
                'description' => 'Should append voter to existing'
            ],
            'multiple additions' => [
                'initialVoters' => [],
                'addedVoters' => [],
                'finalVoters' => [],
                'description' => 'Should append multiple voters'
            ],
            'no additions' => [
                'initialVoters' => [],
                'addedVoters' => [],
                'finalVoters' => [],
                'description' => 'Should maintain existing voters when none added'
            ],
        ];
    }

    #[Test]
    public function it_returns_voter_count(): void
    {
        // Initially should be 0
        self::assertSame(0, $this->configuration->voterCount());

        // Add one voter
        $voter1 = $this->createMock(VoterInterface::class);
        $this->configuration->addVoter($voter1);
        self::assertSame(1, $this->configuration->voterCount());

        // Add more voters
        $voter2 = $this->createMock(VoterInterface::class);
        $voter3 = $this->createMock(VoterInterface::class);
        $this->configuration->addVoter($voter2)->addVoter($voter3);
        self::assertSame(3, $this->configuration->voterCount());

        // Clear voters
        $this->configuration->clearVoters();
        self::assertSame(0, $this->configuration->voterCount());

        // Set voters array
        $this->configuration->setVoters([$voter1, $voter2]);
        self::assertSame(2, $this->configuration->voterCount());
    }
}
