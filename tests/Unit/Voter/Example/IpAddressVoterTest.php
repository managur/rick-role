<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit\Voter\Example;

use PHPUnit\Framework\TestCase;
use RickRole\Voter\Example\IpAddressVoter;

final class IpAddressVoterTest extends TestCase
{
    public function testAllowsExactIp(): void
    {
        $voter = new IpAddressVoter(['192.168.1.10']);
        $result = $voter->vote('user', 'any', '192.168.1.10');
        self::assertTrue($result->isAllow());
    }

    public function testAllowsCidrIp(): void
    {
        $voter = new IpAddressVoter(['192.168.1.0/24']);
        $result = $voter->vote('user', 'any', '192.168.1.99');
        self::assertTrue($result->isAllow());
    }

    public function testDeniesOutsideRange(): void
    {
        $voter = new IpAddressVoter(['10.0.0.0/8']);
        $result = $voter->vote('user', 'any', '192.168.1.1');
        self::assertTrue($result->isDeny());
    }

    public function testAbstainsWhenNoIpProvided(): void
    {
        $voter = new IpAddressVoter(['127.0.0.1']);
        $result = $voter->vote('user', 'any', null);
        self::assertTrue($result->isAbstain());
    }

    public function testDeniesWhenInvalidIpProvided(): void
    {
        $voter = new IpAddressVoter(['127.0.0.1']);
        $result = $voter->vote('user', 'any', 'not-an-ip');
        self::assertTrue($result->isDeny());
    }

    public function testDeniesWhenCidrMaskInvalid(): void
    {
        $voter = new IpAddressVoter(['192.168.1.0/33']);
        $result = $voter->vote('user', 'any', '192.168.1.10');
        self::assertTrue($result->isDeny());
    }

    public function testDeniesWhenIpv6CidrProvided(): void
    {
        $voter = new IpAddressVoter(['::1/128']);
        $result = $voter->vote('user', 'any', '127.0.0.1');
        self::assertTrue($result->isDeny());
    }

    public function testDeniesWhenInvalidIpAgainstCidrRule(): void
    {
        // Exercise the ip2long($ip) === false branch inside CIDR handling
        $voter = new IpAddressVoter(['10.0.0.0/8']);
        $result = $voter->vote('user', 'any', 'not-an-ip');
        self::assertTrue($result->isDeny());
    }
}
