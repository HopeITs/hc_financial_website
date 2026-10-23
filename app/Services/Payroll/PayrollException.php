<?php

namespace App\Services\Payroll;

use RuntimeException;

/** A payroll split or salary allocation the rules refuse. Carries one message per problem. */
class PayrollException extends RuntimeException
{
    /** @param  non-empty-list<string>  $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct($problems[0].(count($problems) > 1 ? ' (+'.(count($problems) - 1).')' : ''));
    }
}
