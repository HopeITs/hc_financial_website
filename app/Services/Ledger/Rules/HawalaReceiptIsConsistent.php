<?php

namespace App\Services\Ledger\Rules;

use App\Enums\VoucherSource;
use App\Services\Ledger\LedgerContext;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherDraft;
use App\Support\Money;

/**
 * Rule 15 (Financial Manager, rounds 1 and 3): a donor receipt through the transfer company keeps the euros sent, the rate
 * they were really converted at and the company's commission. The dollars received must equal euros times rate minus
 * commission, within one cent, and a commission is posted as an organisation expense (5504). Migrated vouchers are history
 * and are not held to it.
 */
final class HawalaReceiptIsConsistent implements PostingRule
{
    public const COMMISSION_ACCOUNT = '5504';

    public function number(): int
    {
        return 15;
    }

    public function check(VoucherDraft $draft, LedgerContext $context): array
    {
        if ($draft->source === VoucherSource::Migration) {
            return [];
        }

        $company = (string) config('ledger.transfer_company_account');
        $violations = [];
        $commission = Money::zero();
        $posted = Money::zero();

        foreach ($draft->lines as $index => $line) {
            if ($line->accountCode === self::COMMISSION_ACCOUNT) {
                $posted = bcadd($posted, $line->debitUsd, 2);
            }

            if ($line->eurAmount !== null) {
                $rate = $line->eurRate ?? '0.000000';

                if ($line->accountCode !== $company || bccomp($line->debitUsd, '0', 2) <= 0) {
                    $violations[] = new Violation($this->number(), __('ledger.rules.hawala_only_receipt', ['account' => $company]), $index + 1);
                } elseif (bccomp($rate, '0', 6) <= 0) {
                    $violations[] = new Violation($this->number(), __('ledger.rules.hawala_needs_rate'), $index + 1);
                } else {
                    $expected = bcsub(bcmul($line->eurAmount, $rate, 6), $line->commissionUsd, 6);

                    if (bccomp(Money::abs(bcsub($expected, $line->debitUsd, 6), 6), '0.01', 6) > 0) {
                        $violations[] = new Violation($this->number(), __('ledger.rules.hawala_mismatch', [
                            'eur' => $line->eurAmount, 'rate' => $rate, 'commission' => $line->commissionUsd,
                            'expected' => Money::normalize($expected), 'received' => $line->debitUsd,
                        ]), $index + 1);
                    }
                }
            }

            $commission = bcadd($commission, $line->commissionUsd, 2);
        }

        if (bccomp($posted, $commission, 2) < 0) {
            $violations[] = new Violation($this->number(), __('ledger.rules.hawala_commission_posting', ['commission' => $commission, 'account' => self::COMMISSION_ACCOUNT, 'posted' => $posted]));
        }

        return $violations;
    }
}
