<?php

namespace App\Services\Treasury;

use RuntimeException;

/** A treasury action the rules refuse. Carries one message per problem. */
class TreasuryException extends RuntimeException
{
    /** @param  list<string>  $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode(' ', $problems));
    }
}
