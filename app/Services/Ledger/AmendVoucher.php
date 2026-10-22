<?php

namespace App\Services\Ledger;

use App\Models\Voucher;
use App\Models\VoucherAudit;
use App\Services\Ledger\Rules\ApprovedChangesAreAudited;
use App\Services\Ledger\Rules\NothingPostsIntoAClosedMonth;
use Illuminate\Support\Facades\DB;

/**
 * Changes the header of a voucher (date, description). A change to an approved voucher needs a reason, never
 * touches a closed month (old or new date) and leaves old value, new value, user and reason in the audit trail.
 */
class AmendVoucher
{
    /** The header fields that may change after posting. */
    private const FIELDS = ['date', 'description'];

    public function __construct(
        private readonly ApprovedChangesAreAudited $audited,
        private readonly NothingPostsIntoAClosedMonth $closedMonth,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     *
     * @throws PostingRuleViolation
     */
    public function handle(Voucher $voucher, array $changes, int $userId, ?string $reason): Voucher
    {
        $changes = array_intersect_key($changes, array_flip(self::FIELDS));

        return DB::transaction(function () use ($voucher, $changes, $userId, $reason) {
            $this->audited->check($voucher, $userId, $reason);
            $this->closedMonth->checkMonth($voucher->date->format('Y-m'));

            if (isset($changes['date'])) {
                $this->closedMonth->checkMonth(date('Y-m', (int) strtotime((string) $changes['date'])));
            }

            foreach ($changes as $field => $new) {
                $old = $voucher->getAttribute($field);
                $oldText = $field === 'date' ? $voucher->date->toDateString() : (string) $old;
                $newText = $field === 'date' ? date('Y-m-d', (int) strtotime((string) $new)) : (string) $new;

                if ($oldText === $newText) {
                    continue;
                }

                $voucher->setAttribute($field, $new);
                VoucherAudit::query()->create([
                    'voucher_id' => $voucher->id, 'action' => 'amended', 'field' => $field,
                    'old_value' => $oldText, 'new_value' => $newText, 'changed_by' => $userId, 'reason' => $reason,
                ]);
            }

            $voucher->save();

            return $voucher;
        });
    }
}
