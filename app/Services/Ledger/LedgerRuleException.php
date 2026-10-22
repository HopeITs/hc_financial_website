<?php

namespace App\Services\Ledger;

use RuntimeException;

class LedgerRuleException extends RuntimeException
{
    /** @param  non-empty-list<Violation>  $violations */
    public function __construct(public readonly array $violations)
    {
        parent::__construct($violations[0]->message.(count($violations) > 1 ? ' (+'.(count($violations) - 1).')' : ''));
    }

    /** @return list<int> */
    public function rules(): array
    {
        return array_values(array_unique(array_map(fn (Violation $v) => $v->rule, $this->violations)));
    }
}
