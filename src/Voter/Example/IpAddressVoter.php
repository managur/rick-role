<?php

declare(strict_types=1);

namespace RickRole\Voter\Example;

use RickRole\Voter\VoteResult;
use RickRole\Voter\VoterInterface;

/**
 * Example Voter: IP address based access control.
 *
 * Allows access only when the provided IP matches one of the allowed IPs or ranges.
 * Ranges are supported in CIDR notation (e.g., 192.168.1.0/24) for IPv4.
 * This class is provided as an EXAMPLE voter and is not required for
 * regular library usage.
 */
final class IpAddressVoter implements VoterInterface
{
    /** @var list<string> */
    private readonly array $allowedCidrsOrIps;

    /**
     * @param list<string> $allowedCidrsOrIps List of exact IPs or CIDR ranges
     */
    public function __construct(array $allowedCidrsOrIps)
    {
        $this->allowedCidrsOrIps = array_values($allowedCidrsOrIps);
    }

    /**
     * The subject is expected to carry an IP string (e.g., from request context).
     * If no subject is provided, abstain.
     *
     * @param string|int $userId
     * @param string|\Stringable $permission
     * @param mixed $subject Expected to be an IP string
     */
    public function vote(string|int $userId, string|\Stringable $permission, mixed $subject = null): VoteResult
    {
        if (!is_string($subject) || $subject === '') {
            return VoteResult::abstain('No IP provided; abstaining');
        }

        $ip = $subject;
        foreach ($this->allowedCidrsOrIps as $allowed) {
            if ($this->ipMatches($ip, $allowed)) {
                return VoteResult::allow(sprintf('IP %s is allowed by rule %s', $ip, $allowed));
            }
        }

        return VoteResult::deny(sprintf('IP %s is not in allowed list', $ip));
    }

    private function ipMatches(string $ip, string $allowed): bool
    {
        // Exact IP
        if (strpos($allowed, '/') === false) {
            return $ip === $allowed;
        }

        // CIDR range
        [$subnet, $mask] = explode('/', $allowed, 2);
        $maskInt = (int) $mask;
        if ($maskInt < 0 || $maskInt > 32) {
            return false;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskLong = -1 << (32 - $maskInt);
        $maskLong &= 0xFFFFFFFF;

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
