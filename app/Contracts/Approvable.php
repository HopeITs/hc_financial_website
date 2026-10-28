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
     * The amount as a decimal string, in USD.
     *
     * @return numeric-string
     */
    public function approvalAmount(): string;
}
