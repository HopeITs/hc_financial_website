<?php

namespace App\Services\Ledger;

use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAudit;
use Illuminate\Support\Facades\Schema;

/**
 * The supporting document of a voucher (finance request of 2026-11-26): its type (invoice, receipt, payslip or other),
 * its number and the description in English, as the donor's financial report lists them. They describe the voucher and
 * never change an amount, so they can be filled in after approval too; a change by hand is kept in the voucher's audit.
 * Until the migration of 2026-11-26 has run there is nowhere to keep them, and nothing is written.
 */
class VoucherDocuments
{
    public const TYPES = ['invoice', 'receipt', 'payslip', 'other'];

    public function available(): bool
    {
        return Schema::hasColumn('gl_vouchers', 'document_type');
    }

    /** Records the document of a voucher just made; empty values leave what is there. */
    public function record(Voucher $voucher, ?string $type, ?string $number, ?string $descriptionEn): void
    {
        if (! $this->available()) {
            return;
        }

        $values = array_filter([
            'document_type' => in_array($type, self::TYPES, true) ? $type : null,
            'document_number' => $this->clean($number, 60),
            'description_en' => $this->clean($descriptionEn, 2000),
        ], fn (?string $value): bool => $value !== null);

        if ($values !== []) {
            Voucher::withTrashed()->whereKey($voucher->id)->update($values);
        }
    }

    /**
     * Changes the document details of a voucher by hand, keeping what changed in its audit.
     *
     * @return list<string> problems; empty when saved
     */
    public function change(Voucher $voucher, User $by, string $type, string $number, string $descriptionEn): array
    {
        if (! $this->available()) {
            return [__('voucher_documents.not_ready')];
        }

        if ($type !== '' && ! in_array($type, self::TYPES, true)) {
            return [__('voucher_documents.bad_type')];
        }

        $new = ['document_type' => $type !== '' ? $type : null, 'document_number' => $this->clean($number, 60), 'description_en' => $this->clean($descriptionEn, 2000)];

        foreach ($new as $field => $value) {
            $old = $voucher->getAttribute($field);

            if ($old !== $value) {
                VoucherAudit::query()->create([
                    'voucher_id' => $voucher->id, 'action' => 'document', 'field' => $field,
                    'old_value' => $old, 'new_value' => $value, 'changed_by' => (int) $by->getAuthIdentifier(),
                ]);
            }
        }

        Voucher::withTrashed()->whereKey($voucher->id)->update($new);

        return [];
    }

    private function clean(?string $text, int $max): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }
}
