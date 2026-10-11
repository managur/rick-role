<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RickRole\Rick;
use RickRole\Configuration;
use RickRole\Reason\Reason;
use RickRole\Strategy\Decision;
use RickRole\Strategy\DenyWinsStrategy;
use RickRole\Strategy\StrategyInterface;
use RickRole\Tests\Unit\Fixture\ReasonCode;
use RickRole\Voter\VoterInterface;
use RickRole\Voter\VoteResult;

/**
 * Unit tests for the Rick-Role Rick class.
 */
final class RickTest extends TestCase
{
    public function testConstructorThrowsConfigurationExceptionWithNoVoters(): void
    {
        // Arrange
        $config = new Configuration();

        // Act & Assert
        $this->expectException(\RickRole\Exception\ConfigurationException::class);
        $this->expectExceptionMessage('No voters configured - Rick-Role requires at least one voter to function properly');

        new Rick($config);
    }

    public function testCanMethodReturnsTrueWhenVoterAllows(): void
    {
        // Arrange
        $voter = $this->createMock(VoterInterface::class);
        $voter->expects($this->once())
            ->method('vote')
            ->with(123, 'test permission', null)
            ->willReturn(VoteResult::allow('Test voter allows'));

        $config = new Configuration();
        $config->setStrategy(new \RickRole\Strategy\AllowWinsStrategy());
        $config->addVoter($voter);
        $rick = new Rick($config);
        $because = null;

        // Act
        $result = $rick->allows(123, 'test permission', null, $because);

        // Assert
        $this->assertTrue($result);
        $this->assertInstanceOf(Reason::class, $because);
        $this->assertSame('allow', $because->decision);

        $message = $because->message;
        $this->assertStringContainsString('Allow wins', $message);
    }

    public function testCanMethodReturnsFalseWhenVoterDenies(): void
    {
        // Arrange
        $voter = $this->createMock(VoterInterface::class);
        $voter->expects($this->once())
            ->method('vote')
            ->with(123, 'test permission', null)
            ->willReturn(VoteResult::deny('Test voter denies'));

        $config = new Configuration();
        $config->addVoter($voter);
        $rick = new Rick($config);
        $because = null;

        // Act
        $result = $rick->allows(123, 'test permission', null, $because);

        // Assert
        $this->assertFalse($result);
        $this->assertInstanceOf(Reason::class, $because);
        $this->assertSame('deny', $because->decision);
    }

    public function testCannotMethodReturnsInverseOfCan(): void
    {
        // Arrange
        $config = new Configuration();

        // Add a voter that denies first
        $denyVoter = $this->createMock(VoterInterface::class);
        $denyVoter->method('vote')->willReturn(VoteResult::deny('Denied'));
        $config->addVoter($denyVoter);

        $rick = new Rick($config);

        // Act & Assert
        $this->assertTrue($rick->disallows(123, 'test permission'));

        // Clear voters and add a voter that allows
        $config->clearVoters();
        $allowVoter = $this->createMock(VoterInterface::class);
        $allowVoter->method('vote')->willReturn(VoteResult::allow('Allowed'));
        $config->addVoter($allowVoter);

        $this->assertFalse($rick->disallows(123, 'test permission'));
    }

    public function testDenyWinsStrategyStopsOnDeny(): void
    {
        // Arrange
        $allowVoter = $this->createMock(VoterInterface::class);
        $allowVoter->method('vote')->willReturn(VoteResult::allow('Allow voter'));

        $denyVoter = $this->createMock(VoterInterface::class);
        $denyVoter->expects($this->once())
            ->method('vote')
            ->willReturn(VoteResult::deny('Deny voter'));

        $neverCalledVoter = $this->createMock(VoterInterface::class);
        $neverCalledVoter->expects($this->never())->method('vote');

        $config = new Configuration();
        $config->setStrategy(new DenyWinsStrategy());
        $config->addVoter($allowVoter);
        $config->addVoter($denyVoter);
        $config->addVoter($neverCalledVoter);

        $rick = new Rick($config);

        // Act
        $result = $rick->allows(123, 'test permission');

        // Assert
        $this->assertFalse($result);
    }

    public function testReasonHoldsEveryVoteInOrder(): void
    {
        // Arrange
        $voter1 = $this->createMock(VoterInterface::class);
        $voter1->method('vote')->willReturn(VoteResult::abstain('Voter 1 abstains'));

        $voter2 = $this->createMock(VoterInterface::class);
        $voter2->method('vote')->willReturn(VoteResult::allow('Voter 2 allows'));

        $config = new Configuration();
        $config->setStrategy(new \RickRole\Strategy\AllowWinsStrategy());
        $config->addVoter($voter1);
        $config->addVoter($voter2);

        $rick = new Rick($config);
        $because = null;

        // Act
        $rick->allows(123, 'test permission', null, $because);

        // Assert
        $this->assertInstanceOf(Reason::class, $because);

        // Check the final decision
        $this->assertSame('allow', $because->decision);

        $message = $because->message;
        $this->assertStringContainsString('Allow wins', $message);

        // Check that both votes were kept, in vote order
        $this->assertCount(2, $because->votes);
        $this->assertSame('abstain', $because->votes[0]->decision);
        $this->assertSame('Voter 1 abstains', $because->votes[0]->message);
        $this->assertSame('allow', $because->votes[1]->decision);
        $this->assertSame('Voter 2 allows', $because->votes[1]->message);
        $this->assertSame($because->votes[1], $because->decidingVote);
    }

    public function testCanAcceptsStringablePermission(): void
    {
        $voter = $this->createMock(VoterInterface::class);
        $voter->expects($this->once())
            ->method('vote')
            ->with(123, $this->callback(function ($permission) {
                return is_string($permission) || (is_object($permission) && method_exists($permission, '__toString'))
                    ? (string)$permission === 'stringable-permission'
                    : false;
            }), null)
            ->willReturn(VoteResult::allow('Allowed'));

        $config = new Configuration();
        $config->addVoter($voter);
        $rick = new Rick($config);
        $because = null;

        $stringablePermission = new class {
            public function __toString(): string
            {
                return 'stringable-permission';
            }
        };

        $result = $rick->allows(123, $stringablePermission, null, $because);
        $this->assertTrue($result);
        $this->assertInstanceOf(Reason::class, $because);
        $this->assertSame('allow', $because->decision);
    }

    public function testCannotAcceptsStringablePermission(): void
    {
        $voter = $this->createMock(VoterInterface::class);
        $voter->expects($this->once())
            ->method('vote')
            ->with(123, $this->callback(function ($permission) {
                return is_string($permission) || (is_object($permission) && method_exists($permission, '__toString'))
                    ? (string)$permission === 'stringable-permission'
                    : false;
            }), null)
            ->willReturn(VoteResult::deny('Denied'));

        $config = new Configuration();
        $config->addVoter($voter);
        $rick = new Rick($config);
        $because = null;

        $stringablePermission = new class {
            public function __toString(): string
            {
                return 'stringable-permission';
            }
        };

        $result = $rick->disallows(123, $stringablePermission, null, $because);
        $this->assertTrue($result);
        $this->assertInstanceOf(Reason::class, $because);
        $this->assertSame('deny', $because->decision);
    }

    #[Test]
    public function it_logs_permission_checks_when_logger_configured(): void
    {
        // Arrange
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        // Expect debug call for voter decision, then log call for final decision
        $logger->expects($this->once())
            ->method('debug')
            ->with(
                $this->equalTo('Voter decision'),
                $this->callback(function (array $context) {
                    return $context['user_id'] === 123 &&
                           $context['permission'] === 'test permission' &&
                           $context['decision'] === 'allow';
                })
            );

        $logger->expects($this->once())
            ->method('log')
            ->with(
                $this->equalTo('info'),
                $this->equalTo('Permission check completed'),
                $this->callback(function (array $context) {
                    return $context['user_id'] === 123 &&
                           $context['permission'] === 'test permission' &&
                           $context['allowed'] === true &&
                           $context['decision'] === 'allow';
                })
            );

        $voter = $this->createMock(VoterInterface::class);
        $voter->method('vote')
            ->willReturn(VoteResult::allow('Test voter allows'));

        $config = new Configuration();
        $config->setLogger($logger);
        $config->addVoter($voter);
        $rick = new Rick($config);

        // Act
        $result = $rick->allows(123, 'test permission');

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_logs_denied_permissions_at_warning_level(): void
    {
        // Arrange
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        // Expect debug call for voter decision, then log call for final decision
        $logger->expects($this->once())
            ->method('debug')
            ->with(
                $this->equalTo('Voter decision'),
                $this->callback(function (array $context) {
                    return $context['user_id'] === 123 &&
                           $context['permission'] === 'test permission' &&
                           $context['decision'] === 'deny';
                })
            );

        $logger->expects($this->once())
            ->method('log')
            ->with(
                $this->equalTo('warning'),
                $this->equalTo('Permission check completed'),
                $this->callback(function (array $context) {
                    return $context['user_id'] === 123 &&
                           $context['permission'] === 'test permission' &&
                           $context['allowed'] === false &&
                           $context['decision'] === 'deny';
                })
            );

        $voter = $this->createMock(VoterInterface::class);
        $voter->method('vote')
            ->willReturn(VoteResult::deny('Test voter denies'));

        $config = new Configuration();
        $config->setLogger($logger);
        $config->addVoter($voter);
        $rick = new Rick($config);

        // Act
        $result = $rick->allows(123, 'test permission');

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function it_throws_configuration_exception_when_no_voters_configured(): void
    {
        // Arrange
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $config = new Configuration();
        $config->setLogger($logger);

        // Act & Assert
        $this->expectException(\RickRole\Exception\ConfigurationException::class);
        $this->expectExceptionMessage('No voters configured - Rick-Role requires at least one voter to function properly');

        new Rick($config);
    }

    #[Test]
    public function it_does_not_log_when_no_logger_configured(): void
    {
        // Arrange
        $voter = $this->createMock(VoterInterface::class);
        $voter->method('vote')
            ->willReturn(VoteResult::allow('Test voter allows'));

        $config = new Configuration();
        $config->addVoter($voter);
        $rick = new Rick($config);

        // Act & Assert - should not throw any logging-related errors
        $result = $rick->allows(123, 'test permission');
        $this->assertTrue($result);
    }

    public function testDisallowsReturnsTrueWhenNotAllowed(): void
    {
        $voter = $this->createMock(VoterInterface::class);
        $voter->method('vote')->willReturn(VoteResult::deny('Denied'));
        $config = new Configuration();
        $config->addVoter($voter);
        $rick = new Rick($config);
        $because = null;
        $this->assertTrue($rick->disallows(123, 'test permission', null, $because));
        $this->assertInstanceOf(Reason::class, $because);
        $this->assertSame('deny', $because->decision);
    }

    public function testDisallowsReturnsFalseWhenAllowed(): void
    {
        $voter = $this->createMock(VoterInterface::class);
        $voter->method('vote')->willReturn(VoteResult::allow('Allowed'));
        $config = new Configuration();
        $config->addVoter($voter);
        $rick = new Rick($config);
        $because = null;
        $this->assertFalse($rick->disallows(123, 'test permission', null, $because));
        $this->assertInstanceOf(Reason::class, $because);
        $this->assertSame('allow', $because->decision);
    }

    public function testDoesNotAllowIsAliasForDisallows(): void
    {
        $voter = $this->createMock(VoterInterface::class);
        $voter->method('vote')->willReturn(VoteResult::deny('Denied'));
        $config = new Configuration();
        $config->addVoter($voter);
        $rick = new Rick($config);
        $because1 = null;
        $because2 = null;
        $resultDisallows = $rick->disallows(123, 'test permission', null, $because1);
        $resultDoesNotAllow = $rick->doesNotAllow(123, 'test permission', null, $because2);
        $this->assertSame($resultDisallows, $resultDoesNotAllow);
        $this->assertInstanceOf(Reason::class, $because2);
        $this->assertSame('deny', $because2->decision);
    }

    public function testFormatSubjectCoversAllBranches(): void
    {
        $config = new Configuration();
        $voter = $this->createMock(VoterInterface::class);
        $voter->method('vote')->willReturn(VoteResult::allow('Test voter'));
        $config->addVoter($voter);

        $rick = new \RickRole\Rick($config);
        $reflection = new \ReflectionClass($rick);
        $method = $reflection->getMethod('formatSubject');
        $method->setAccessible(true);

        // null
        $this->assertSame('null', $method->invoke($rick, null));
        // string
        $this->assertSame('foo', $method->invoke($rick, 'foo'));
        // numeric
        $this->assertSame('42', $method->invoke($rick, 42));
        $this->assertSame('3.14', $method->invoke($rick, 3.14));
        // object
        $obj = new \stdClass();
        $result = $method->invoke($rick, $obj);
        $this->assertIsString($result);
        $this->assertStringStartsWith('stdClass#', $result);
        // array
        $this->assertSame('array(2)', $method->invoke($rick, [1,2]));
        // other type (e.g., resource)
        $resource = fopen('php://memory', 'r');
        if ($resource === false) {
            $this->fail('Failed to create test resource');
        }
        $this->assertSame('resource', $method->invoke($rick, $resource));
        fclose($resource);
    }

    public function testCodeOfDecidingDenyReachesReasonWithDenyWinsStrategy(): void
    {
        // Arrange
        $allowVoter = $this->voterReturning('RickTestRoleVoter', VoteResult::allow('Role allows it', code: 'role.allow'));
        $mfaVoter = $this->voterReturning('RickTestMfaVoter', VoteResult::deny('MFA is mandatory', code: ReasonCode::Mandatory));

        $config = new Configuration();
        $config->setVoters([$allowVoter, $mfaVoter]);
        $rick = new Rick($config);
        $reason = null;

        // Act
        $result = $rick->disallows(userId: 123, to: 'mfa.disable.submit', because: $reason);

        // Assert
        $this->assertTrue($result);
        $this->assertInstanceOf(Reason::class, $reason);
        $this->assertSame(ReasonCode::Mandatory, $reason->code);
        $this->assertTrue($reason->is(ReasonCode::Mandatory));
        $this->assertTrue($reason->wasDecidedBy($mfaVoter::class));
        $this->assertTrue($reason->wasNotDecidedBy($allowVoter::class));
        $this->assertSame('MFA is mandatory', $reason->given());
        $this->assertSame('Deny wins - MFA is mandatory', $reason->message);
    }

    public function testCodeOfDecidingAllowReachesReasonWithAllowWinsStrategy(): void
    {
        // Arrange
        $denyVoter = $this->voterReturning('RickTestDenyVoter', VoteResult::deny('Role denies it', code: 'role.deny'));
        $allowVoter = $this->voterReturning('RickTestAllowVoter', VoteResult::allow('Role allows it', code: 'role.allow'));

        $config = new Configuration();
        $config->setStrategy(new \RickRole\Strategy\AllowWinsStrategy());
        $config->setVoters([$denyVoter, $allowVoter]);
        $rick = new Rick($config);
        $reason = null;

        // Act
        $result = $rick->allows(123, 'edit post', null, $reason);

        // Assert
        $this->assertTrue($result);
        $this->assertInstanceOf(Reason::class, $reason);
        $this->assertSame('role.allow', $reason->code);
        $this->assertTrue($reason->wasDecidedBy($allowVoter::class));
        $this->assertTrue($reason->wasNotDecidedBy($denyVoter::class));
        $this->assertSame('Role allows it', $reason->given());
    }

    public function testFirstMatchingVoteDecidesWhenSeveralAllow(): void
    {
        // Arrange
        $firstVoter = $this->voterReturning('RickTestFirstVoter', VoteResult::allow('First allows it', code: 'first.allow'));
        $secondVoter = $this->voterReturning('RickTestSecondVoter', VoteResult::allow('Second allows it', code: 'second.allow'));

        $config = new Configuration();
        $config->setVoters([$firstVoter, $secondVoter]);
        $rick = new Rick($config);
        $reason = null;

        // Act
        $rick->allows(123, 'edit post', null, $reason);

        // Assert
        $this->assertInstanceOf(Reason::class, $reason);
        $this->assertSame('first.allow', $reason->code);
        $this->assertTrue($reason->wasDecidedBy($firstVoter::class));
        $this->assertTrue($reason->wasNotDecidedBy($secondVoter::class));
    }

    public function testNoVoteDecidesWhenAllAbstain(): void
    {
        // Arrange
        $voter = $this->voterReturning('RickTestAbstainVoter', VoteResult::abstain('Not my permission', code: 'not.mine'));

        $config = new Configuration();
        $config->addVoter($voter);
        $rick = new Rick($config);
        $reason = null;

        // Act
        $result = $rick->allows(123, 'edit post', null, $reason);

        // Assert
        $this->assertFalse($result);
        $this->assertInstanceOf(Reason::class, $reason);
        $this->assertTrue($reason->isDeny());
        $this->assertNull($reason->decidingVote);
        $this->assertNull($reason->code);
        $this->assertTrue($reason->has('not.mine'));
        $this->assertTrue($reason->wasNotDecidedBy($voter::class));
        $this->assertSame('All voters abstained', $reason->given());
    }

    public function testCodeOfDecidingVoteReachesReasonWhenStrategyChangesTheMessage(): void
    {
        // Arrange
        $strategy = new class implements StrategyInterface {
            public function decide(array $voteResults): Decision
            {
                return Decision::byVote($voteResults[0], 'Custom strategy denies');
            }

            public function shouldStopVoting(VoteResult $voteResult): bool
            {
                return false;
            }
        };
        $voter = $this->voterReturning('RickTestCustomStrategyVoter', VoteResult::deny('MFA is mandatory', code: ReasonCode::Mandatory));

        $config = new Configuration();
        $config->setStrategy($strategy);
        $config->addVoter($voter);
        $rick = new Rick($config);
        $reason = null;

        // Act
        $rick->allows(123, 'mfa.disable.submit', null, $reason);

        // Assert
        $this->assertInstanceOf(Reason::class, $reason);
        $this->assertSame(ReasonCode::Mandatory, $reason->code);
        $this->assertTrue($reason->wasDecidedBy($voter::class));
    }

    public function testCodeOfNestedVoteReachesReason(): void
    {
        // Arrange
        $nestedVoter = new class implements VoterInterface {
            public function vote(string|int $userId, string|\Stringable $permission, mixed $subject = null): VoteResult
            {
                return (new DenyWinsStrategy())->decide([
                    VoteResult::allow('Role A allows it'),
                    VoteResult::deny('Role B denies it', code: 'role.deny'),
                ])->result;
            }
        };

        $config = new Configuration();
        $config->addVoter($nestedVoter);
        $rick = new Rick($config);
        $reason = null;

        // Act
        $rick->allows(123, 'edit post', null, $reason);

        // Assert
        $this->assertInstanceOf(Reason::class, $reason);
        $this->assertSame('role.deny', $reason->code);
        $this->assertTrue($reason->wasDecidedBy($nestedVoter::class));
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function voterOrders(): array
    {
        return [
            'role voter first' => [false],
            'mfa voter first' => [true],
        ];
    }

    #[DataProvider('voterOrders')]
    public function testHasCodeOfEveryAllowWhateverTheVoterOrder(bool $mfaFirst): void
    {
        // Arrange
        $suffix = $mfaFirst ? 'MfaFirst' : 'RoleFirst';
        $roleVoter = $this->voterReturning('RickTestHasRoleVoter' . $suffix, VoteResult::allow('Role allows it', code: 'signed_in'));
        $mfaVoter = $this->voterReturning('RickTestHasMfaVoter' . $suffix, VoteResult::allow('Cleanup is allowed', code: ReasonCode::Expired));

        $config = new Configuration();
        $config->setVoters($mfaFirst ? [$mfaVoter, $roleVoter] : [$roleVoter, $mfaVoter]);
        $rick = new Rick($config);
        $reason = null;

        // Act
        $result = $rick->allows(userId: 123, to: 'mfa.disable.submit', because: $reason);

        // Assert
        $this->assertTrue($result);
        $this->assertInstanceOf(Reason::class, $reason);
        $this->assertTrue($reason->has('signed_in'));
        $this->assertTrue($reason->has(ReasonCode::Expired));
        $this->assertTrue($reason->wasComplete());
        $this->assertSame($mfaFirst ? ReasonCode::Expired : 'signed_in', $reason->code);
        $this->assertSame(
            $mfaFirst ? [ReasonCode::Expired, 'signed_in'] : ['signed_in', ReasonCode::Expired],
            $reason->codes()
        );
    }

    #[DataProvider('voterOrders')]
    public function testHasCodeOfAnAbstainingVoteWhateverTheVoterOrder(bool $mfaFirst): void
    {
        // Arrange
        $suffix = $mfaFirst ? 'MfaFirst' : 'RoleFirst';
        $roleVoter = $this->voterReturning('RickTestAbstainRoleVoter' . $suffix, VoteResult::allow('Role allows it', code: 'signed_in'));
        $mfaVoter = $this->voterReturning('RickTestAbstainMfaVoter' . $suffix, VoteResult::abstain('MFA is off, so only cleanup', code: ReasonCode::Cleanup));

        $config = new Configuration();
        $config->setVoters($mfaFirst ? [$mfaVoter, $roleVoter] : [$roleVoter, $mfaVoter]);
        $rick = new Rick($config);
        $reason = null;

        // Act
        $result = $rick->allows(userId: 123, to: 'mfa.disable.submit', because: $reason);

        // Assert
        $this->assertTrue($result);
        $this->assertInstanceOf(Reason::class, $reason);
        $this->assertTrue($reason->wasDecidedBy($roleVoter::class));
        $this->assertTrue($reason->is('signed_in'));
        $this->assertTrue($reason->isNot(ReasonCode::Cleanup));
        $this->assertTrue($reason->has(ReasonCode::Cleanup));
    }

    public function testHasCodeOfCodedAllowAfterUncodedAllow(): void
    {
        // Arrange
        $roleVoter = $this->voterReturning('RickTestUncodedRoleVoter', VoteResult::allow('Role allows it'));
        $mfaVoter = $this->voterReturning('RickTestCodedMfaVoter', VoteResult::allow('Cleanup is allowed', code: ReasonCode::Expired));

        $config = new Configuration();
        $config->setVoters([$roleVoter, $mfaVoter]);
        $rick = new Rick($config);
        $reason = null;

        // Act
        $rick->allows(123, 'mfa.disable.submit', null, $reason);

        // Assert
        $this->assertInstanceOf(Reason::class, $reason);
        $this->assertNull($reason->code);
        $this->assertTrue($reason->has(ReasonCode::Expired));
        $this->assertSame([ReasonCode::Expired], $reason->codes());
        $this->assertCount(2, $reason->votes);
    }

    public function testHasOnlySeesVotesBeforeDenyWinsStopsEarly(): void
    {
        // Arrange
        $roleVoter = $this->voterReturning('RickTestEarlyRoleVoter', VoteResult::allow('Role allows it', code: 'role.allow'));
        $mfaVoter = $this->voterReturning('RickTestEarlyMfaVoter', VoteResult::deny('MFA is mandatory', code: ReasonCode::Mandatory));
        $laterVoter = $this->createMock(VoterInterface::class);
        $laterVoter->expects($this->never())->method('vote');

        $config = new Configuration();
        $config->setVoters([$roleVoter, $mfaVoter, $laterVoter]);
        $rick = new Rick($config);
        $reason = null;

        // Act
        $result = $rick->allows(123, 'mfa.disable.submit', null, $reason);

        // Assert
        $this->assertFalse($result);
        $this->assertInstanceOf(Reason::class, $reason);
        $this->assertTrue($reason->wasNotComplete());
        $this->assertTrue($reason->has(ReasonCode::Mandatory));
        $this->assertTrue($reason->has('role.allow'));
        $this->assertSame(['role.allow', ReasonCode::Mandatory], $reason->codes());
        $this->assertCount(2, $reason->votes);
    }

    public function testHasOnlySeesVotesBeforeAllowWinsStopsEarly(): void
    {
        // Arrange
        $denyVoter = $this->voterReturning('RickTestEarlyDenyVoter', VoteResult::deny('Role denies it', code: 'role.deny'));
        $allowVoter = $this->voterReturning('RickTestEarlyAllowVoter', VoteResult::allow('Role allows it', code: 'role.allow'));
        $laterVoter = $this->createMock(VoterInterface::class);
        $laterVoter->expects($this->never())->method('vote');

        $config = new Configuration();
        $config->setStrategy(new \RickRole\Strategy\AllowWinsStrategy());
        $config->setVoters([$denyVoter, $allowVoter, $laterVoter]);
        $rick = new Rick($config);
        $reason = null;

        // Act
        $result = $rick->allows(123, 'edit post', null, $reason);

        // Assert
        $this->assertTrue($result);
        $this->assertInstanceOf(Reason::class, $reason);
        $this->assertTrue($reason->wasNotComplete());
        $this->assertTrue($reason->is('role.allow'));
        $this->assertTrue($reason->has('role.deny'));
        $this->assertSame(['role.deny', 'role.allow'], $reason->codes());
    }

    public function testDeniedDecisionIsCompleteWhenTheLastVoterDenies(): void
    {
        // Arrange
        $roleVoter = $this->voterReturning('RickTestLastRoleVoter', VoteResult::allow('Role allows it'));
        $mfaVoter = $this->voterReturning('RickTestLastMfaVoter', VoteResult::deny('MFA is mandatory', code: ReasonCode::Mandatory));

        $config = new Configuration();
        $config->setVoters([$roleVoter, $mfaVoter]);
        $rick = new Rick($config);
        $reason = null;

        // Act
        $rick->allows(123, 'mfa.disable.submit', null, $reason);

        // Assert
        $this->assertInstanceOf(Reason::class, $reason);
        $this->assertTrue($reason->wasComplete());
    }

    public function testHasDoesNotConfuseEnumAndStringCodes(): void
    {
        // Arrange
        $stringVoter = $this->voterReturning('RickTestStringCodeVoter', VoteResult::allow('String code', code: 'mandatory'));
        $enumVoter = $this->voterReturning('RickTestEnumCodeVoter', VoteResult::allow('Enum code', code: ReasonCode::Expired));

        $config = new Configuration();
        $config->setVoters([$stringVoter, $enumVoter]);
        $rick = new Rick($config);
        $reason = null;

        // Act
        $rick->allows(123, 'edit post', null, $reason);

        // Assert
        $this->assertInstanceOf(Reason::class, $reason);
        $this->assertTrue($reason->has('mandatory'));
        $this->assertTrue($reason->hasNot(ReasonCode::Mandatory));
        $this->assertTrue($reason->has(ReasonCode::Expired));
        $this->assertTrue($reason->hasNot('expired'));
    }

    public function testReusedReasonDoesNotCarryCodesFromAnEarlierCheck(): void
    {
        // Arrange
        $firstConfig = new Configuration();
        $firstConfig->addVoter($this->voterReturning('RickTestFirstCheckVoter', VoteResult::deny('Platform disabled', code: 'platform.disabled')));
        $secondConfig = new Configuration();
        $secondConfig->addVoter($this->voterReturning('RickTestSecondCheckVoter', VoteResult::allow('Role allows it', code: 'signed_in')));
        $reason = null;

        // Act
        (new Rick($firstConfig))->allows(123, 'mfa.replace.submit', null, $reason);
        (new Rick($secondConfig))->allows(123, 'mfa.disable.submit', null, $reason);

        // Assert
        $this->assertInstanceOf(Reason::class, $reason);
        $this->assertSame('mfa.disable.submit', $reason->permission);
        $this->assertTrue($reason->hasNot('platform.disabled'));
        $this->assertSame(['signed_in'], $reason->codes());
        $this->assertCount(1, $reason->votes);
    }

    public function testHasCodeGivenByTheStrategyItself(): void
    {
        // Arrange
        $strategy = new class implements StrategyInterface {
            public function decide(array $voteResults): Decision
            {
                return Decision::byStrategy(VoteResult::allow('Strategy allows it', code: 'strategy.allow'));
            }

            public function shouldStopVoting(VoteResult $voteResult): bool
            {
                return false;
            }
        };
        $voter = $this->voterReturning('RickTestStrategyCodeVoter', VoteResult::allow('Voter allows it', code: 'voter.allow'));

        $config = new Configuration();
        $config->setStrategy($strategy);
        $config->addVoter($voter);
        $rick = new Rick($config);
        $reason = null;

        // Act
        $rick->allows(123, 'edit post', null, $reason);

        // Assert
        $this->assertInstanceOf(Reason::class, $reason);
        $this->assertNull($reason->decidingVote);
        $this->assertTrue($reason->is('strategy.allow'));
        $this->assertTrue($reason->has('strategy.allow'));
        $this->assertTrue($reason->has('voter.allow'));
        $this->assertSame(['voter.allow'], $reason->codes());
    }

    public function testStrategyChoosesTheDecidingVote(): void
    {
        // Arrange
        $strategy = new class implements StrategyInterface {
            public function decide(array $voteResults): Decision
            {
                $last = max(0, count($voteResults) - 1);

                return Decision::byVote($voteResults[$last], 'Last deny wins - ' . $voteResults[$last]->message());
            }

            public function shouldStopVoting(VoteResult $voteResult): bool
            {
                return false;
            }
        };
        $firstVoter = $this->voterReturning('RickTestFirstDenyVoter', VoteResult::deny('First denies it', code: 'first.deny'));
        $lastVoter = $this->voterReturning('RickTestLastDenyVoter', VoteResult::deny('Last denies it', code: 'last.deny'));

        $config = new Configuration();
        $config->setStrategy($strategy);
        $config->setVoters([$firstVoter, $lastVoter]);
        $rick = new Rick($config);
        $reason = null;

        // Act
        $rick->allows(123, 'edit post', null, $reason);

        // Assert
        $this->assertInstanceOf(Reason::class, $reason);
        $this->assertTrue($reason->wasDecidedBy($lastVoter::class));
        $this->assertTrue($reason->is('last.deny'));
        $this->assertSame('Last denies it', $reason->given());
        $this->assertTrue($reason->has('first.deny'));
    }

    public function testStrategyDecidingByAVoteThatWasNotCastIsRejected(): void
    {
        // Arrange
        $strategy = new class implements StrategyInterface {
            public function decide(array $voteResults): Decision
            {
                return Decision::byVote(VoteResult::allow('Made up by the strategy'));
            }

            public function shouldStopVoting(VoteResult $voteResult): bool
            {
                return false;
            }
        };

        $config = new Configuration();
        $config->setStrategy($strategy);
        $config->addVoter($this->voterReturning('RickTestOnlyVoter', VoteResult::allow('Allowed')));
        $rick = new Rick($config);

        // Assert
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('decided by a vote that was not cast in this check');

        // Act
        $rick->allows(123, 'edit post');
    }

    public function testStrategyMayFilterAndReorderVotesBeforeDeciding(): void
    {
        // Arrange
        $strategy = new class implements StrategyInterface {
            public function decide(array $voteResults): Decision
            {
                $decisive = array_reverse(array_values(array_filter(
                    $voteResults,
                    static fn(VoteResult $voteResult): bool => $voteResult->isAbstain() === false
                )));

                return Decision::byVote($decisive[0]);
            }

            public function shouldStopVoting(VoteResult $voteResult): bool
            {
                return false;
            }
        };
        $abstainVoter = $this->voterReturning('RickTestReorderAbstainVoter', VoteResult::abstain('Not mine'));
        $firstVoter = $this->voterReturning('RickTestReorderFirstVoter', VoteResult::allow('First allows it', code: 'first.allow'));
        $lastVoter = $this->voterReturning('RickTestReorderLastVoter', VoteResult::allow('Last allows it', code: 'last.allow'));

        $config = new Configuration();
        $config->setStrategy($strategy);
        $config->setVoters([$abstainVoter, $firstVoter, $lastVoter]);
        $rick = new Rick($config);
        $reason = null;

        // Act
        $rick->allows(123, 'edit post', null, $reason);

        // Assert
        $this->assertInstanceOf(Reason::class, $reason);
        $this->assertTrue($reason->wasDecidedBy($lastVoter::class));
        $this->assertSame($reason->votes[2], $reason->decidingVote);
        $this->assertTrue($reason->is('last.allow'));
        $this->assertSame('Last allows it', $reason->given());
    }

    private function voterReturning(string $className, VoteResult $voteResult): VoterInterface
    {
        // @phpstan-ignore-next-line
        $voter = $this->getMockBuilder(VoterInterface::class)->setMockClassName($className)->getMock();
        $voter->method('vote')->willReturn($voteResult);

        return $voter;
    }
}
