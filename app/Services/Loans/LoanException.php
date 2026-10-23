<?php

namespace App\Services\Loans;

use RuntimeException;

/** A micro loan or repayment the rules refuse. Carries one message per problem. */
class LoanException extends RuntimeException
{
    /** @param  non-empty-list<string>  $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct($problems[0].(count($problems) > 1 ? ' (+'.(count($problems) - 1).')' : ''));
    }
}
