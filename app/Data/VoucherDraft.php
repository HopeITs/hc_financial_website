<?php

namespace App\Data;

use App\Enums\VoucherType;
use App\Services\Ledger\PostVoucher;
use Carbon\CarbonInterface;

/**
 * A voucher as the builder (or the importer) hands it to {@see PostVoucher}.
 */
final readonly class VoucherDraft
{
    /**
     * @param  list<LineDraft>  $lines
     */
    public function __construct(
        public VoucherType $type,
        public CarbonInterface $date,
        public int $preparedBy,
        public array $lines,
        public ?string $description = null,
        public ?string $transferKind = null,
        public ?int $requestId = null,
        public string $source = 'web',
        public ?int $legacyActionId = null,
        public bool $isCarryForward = false,
        public bool $isTemporary = false,
    ) {}

    public function month(): string
    {
        return $this->date->format('Y-m');
    }

    /**
     * @return numeric-string
     */
    public function totalDebitUsd(): string
    {
        return array_reduce($this->lines, fn (string $sum, LineDraft $l) => bcadd($sum, $l->amount('debit_usd'), 2), '0.00');
    }

    /**
     * @return numeric-string
     */
    public function totalCreditUsd(): string
    {
        return array_reduce($this->lines, fn (string $sum, LineDraft $l) => bcadd($sum, $l->amount('credit_usd'), 2), '0.00');
    }
}
