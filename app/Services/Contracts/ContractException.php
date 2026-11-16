<?php

namespace App\Services\Contracts;

use RuntimeException;

/** A contract action the rules refuse. Carries one message per problem. */
class ContractException extends RuntimeException
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode(' ', $problems));
    }
}
