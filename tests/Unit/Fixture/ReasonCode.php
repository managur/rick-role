<?php

declare(strict_types=1);

namespace RickRole\Tests\Unit\Fixture;

/**
 * Backed enum used as a reason code in tests.
 */
enum ReasonCode: string
{
    case Mandatory = 'mandatory';
    case Expired = 'expired';
}
