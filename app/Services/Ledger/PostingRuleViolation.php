<?php

namespace App\Services\Ledger;

use DomainException;

/**
 * A posting rule refused the voucher. `$rule` is the key under `ledger.rules` in the lang files.
 */
class PostingRuleViolation extends DomainException
{
    /**
     * @param  array<string, scalar|null>  $context
     */
    public function __construct(public readonly string $rule, public readonly array $context = [])
    {
        parent::__construct(__('ledger.rules.'.$rule, $context));
    }

    /**
     * @param  array<string, scalar|null>  $context
     */
    public static function of(string $rule, array $context = []): self
    {
        return new self($rule, $context);
    }
}
