<?php

namespace App\Billing;

use RuntimeException;

final class InsufficientCredits extends RuntimeException
{
    public function __construct(public readonly int $required, public readonly int $available)
    {
        parent::__construct(sprintf(
            'This generation can cost up to %s, but you have %s available. Buy credits to continue.',
            Money::format($required),
            Money::format(max(0, $available)),
        ));
    }
}
