<?php

namespace App\Services\Excel;

use RuntimeException;

/** An Excel import the rules refuse. Carries one message per problem. */
class ExcelException extends RuntimeException
{
    /** @param  list<string>  $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode(' ', $problems));
    }
}
