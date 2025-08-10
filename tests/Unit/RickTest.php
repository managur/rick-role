<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RickRole\Rick;
use RickRole\Configuration;
use RickRole\Reason\Reason;
use RickRole\Strategy\DenyWinsStrategy;
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
        $this->assertNotNull($message);
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

    public function testReasonChainingWorksCorrectly(): void
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
        $this->assertNotNull($message);
        $this->assertStringContainsString('Allow wins', $message);

        // Check that we have a previous reason (voter2)
        $this->assertNotNull($because->previous);
        $this->assertSame('allow', $because->previous->decision);

        $previousMessage = $because->previous->message;
        $this->assertNotNull($previousMessage);
        $this->assertStringContainsString('Voter 2 allows', $previousMessage);

        // Check that we have another previous reason (voter1)
        $this->assertNotNull($because->previous->previous);
        $this->assertSame('abstain', $because->previous->previous->decision);

        $voter1Message = $because->previous->previous->message;
        $this->assertNotNull($voter1Message);
        $this->assertStringContainsString('Voter 1 abstains', $voter1Message);
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
}
