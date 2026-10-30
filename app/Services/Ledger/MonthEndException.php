<?php

namespace App\Services\Ledger;

use RuntimeException;

/** A month-end step that cannot be done; the message says why, in the person's language. */
class MonthEndException extends RuntimeException
{
    /** @param  list<string>  $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode(' ', $problems));
    }
}
