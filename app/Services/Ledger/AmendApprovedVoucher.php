<?php

namespace App\Services\Ledger;

use App\Enums\VoucherStatus;
use App\Models\Voucher;
use App\Models\VoucherAudit;
use App\Services\Ledger\Rules\MonthIsOpen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rule 9: a change to an approved voucher keeps the old value, the new value, who changed it and why. This
 * covers the voucher's header (date and description); a month that is closed refuses the change (rule 8).
 */
class AmendApprovedVoucher
{
    private const FIELDS = ['date', 'description'];

    public function __construct(private readonly MonthIsOpen $monthIsOpen) {}

    /**
     * @param  array<string, string|null>  $changes  field => new value
     * @param  int  $userId  the legacy `user`.`ID` of the person changing it
     *
     * @throws LedgerRuleException
     */
    public function amend(Voucher $voucher, array $changes, int $userId, ?string $reason = null): Voucher
    {
        $violations = [];

        foreach (array_diff(array_keys($changes), self::FIELDS) as $field) {
            $violations[] = new Violation(9, __('ledger.rules.amend_field', ['field' => $field]));
        }

        $new = [];
        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $changes)) {
                continue;
            }

            $value = $field === 'date' ? Carbon::parse((string) $changes[$field])->toDateString() : $changes[$field];
            $old = $field === 'date' ? $voucher->date->toDateString() : $voucher->description;

            if ($value !== $old) {
                $new[$field] = ['old' => $old, 'new' => $value];
            }
        }

        if ($violations === [] && $new === []) {
            return $voucher;
        }

        $months = array_unique([$voucher->date->format('Y-m'), ...(isset($new['date']) ? [Carbon::parse($new['date']['new'])->format('Y-m')] : [])]);
        foreach ($months as $month) {
            array_push($violations, ...$this->monthIsOpen->checkMonth($month));
        }

        $audited = $voucher->status !== VoucherStatus::PendingApproval;
        if ($audited && trim((string) $reason) === '') {
            $violations[] = new Violation(9, __('ledger.rules.amend_reason'));
        }

        if ($violations !== []) {
            throw new LedgerRuleException($violations);
        }

        return DB::transaction(function () use ($voucher, $new, $userId, $reason, $audited) {
            $attributes = [];

            foreach ($new as $field => $values) {
                if ($audited) {
                    VoucherAudit::query()->create([
                        'voucher_id' => $voucher->id, 'action' => 'amend', 'field' => $field,
                        'old_value' => $values['old'], 'new_value' => $values['new'], 'changed_by' => $userId, 'reason' => $reason,
                    ]);
                }

                $attributes[$field] = $values['new'];
            }

            $voucher->update($attributes);

            return $voucher->refresh();
        });
    }
}
