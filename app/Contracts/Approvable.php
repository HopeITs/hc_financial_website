<?php

namespace App\Contracts;

/**
 * Something that needs approval (a voucher, a request) and is checked by the approval policy.
 */
interface Approvable
{
    /**
     * The `user`.`ID` of the person who prepared it. They can never approve it.
     */
    public function preparedBy(): int;

    /**
     * The amount as a decimal string, in the currency of {@see self::approvalCurrencyId()}.
     *
     * @return numeric-string
     */
    public function approvalAmount(): string;

    /**
     * The `tilltypes`.`ID` of the amount's currency (the key of `role_currency_limit`).
     */
    public function approvalCurrencyId(): ?int;
}
