<?php

namespace App\Services\Entries;

use RuntimeException;

/** An entry the rules refuse. Carries one message per problem. */
class EntryException extends RuntimeException
{
    /** @param  list<string>  $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode(' ', $problems));
    }
}
