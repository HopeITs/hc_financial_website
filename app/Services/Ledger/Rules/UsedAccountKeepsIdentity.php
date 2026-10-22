<?php

namespace App\Services\Ledger\Rules;

use App\Models\GlAccount;
use App\Models\VoucherLine;
use App\Services\Ledger\Violation;
use BackedEnum;

/**
 * Rule 11: once a voucher line uses an account, its code, class and currency never change (it can still be
 * renamed or deactivated).
 */
final class UsedAccountKeepsIdentity
{
    private const PROTECTED = ['code', 'class', 'currency'];

    public function number(): int
    {
        return 11;
    }

    /**
     * @param  array<string, mixed>  $changes  the attributes about to be saved, by column name
     * @return list<Violation>
     */
    public function check(GlAccount $account, array $changes): array
    {
        $changed = array_filter(
            self::PROTECTED,
            fn (string $field) => array_key_exists($field, $changes)
                && $this->plain($changes[$field]) !== $this->plain($account->getOriginal($field)),
        );

        if ($changed === [] || ! VoucherLine::query()->where('account_code', $account->getOriginal('code'))->exists()) {
            return [];
        }

        return array_map(
            fn (string $field) => new Violation($this->number(), __('ledger.rules.used_account', ['account' => $account->getOriginal('code'), 'field' => $field])),
            array_values($changed),
        );
    }

    private function plain(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
