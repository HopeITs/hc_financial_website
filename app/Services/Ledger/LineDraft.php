<?php

namespace App\Services\Ledger;

use App\Enums\FundSource;
use App\Support\Money;

/**
 * One line of a voucher about to be posted. Amounts are decimal strings; USD lines take their USD amount
 * from the original amount when it is not given.
 */
final readonly class LineDraft
{
    /** @var numeric-string */
    public string $debitOrig;

    /** @var numeric-string */
    public string $creditOrig;

    /** @var numeric-string */
    public string $rate;

    /** @var numeric-string */
    public string $debitUsd;

    /** @var numeric-string */
    public string $creditUsd;

    /** @var numeric-string */
    public string $debitDonor;

    /** @var numeric-string */
    public string $creditDonor;

    public function __construct(
        public string $accountCode,
        public string $currency,
        string|int|float|null $debit = null,
        string|int|float|null $credit = null,
        string|int|float|null $rate = 1,
        string|int|float|null $debitUsd = null,
        string|int|float|null $creditUsd = null,
        public ?string $projectCode = null,
        public ?string $budgetLineKey = null,
        public ?FundSource $fundSource = null,
        public ?string $contact = null,
        public ?string $center = null,
        public ?string $donor = null,
        public ?string $city = null,
        public ?string $restriction = null,
        public ?string $reason = null,
        public ?string $liraType = null,
        public ?string $donorCurrency = null,
        string|int|float|null $debitDonor = null,
        string|int|float|null $creditDonor = null,
    ) {
        $this->debitOrig = Money::normalize($debit);
        $this->creditOrig = Money::normalize($credit);
        $this->rate = Money::normalize($rate, 6);
        $this->debitUsd = Money::normalize($debitUsd ?? ($currency === 'USD' ? $debit : null));
        $this->creditUsd = Money::normalize($creditUsd ?? ($currency === 'USD' ? $credit : null));
        $this->debitDonor = Money::normalize($debitDonor);
        $this->creditDonor = Money::normalize($creditDonor);
    }

    /** The value of one of the five tags an account can require, or null when it is not filled. */
    public function tag(string $tag): ?string
    {
        $value = match ($tag) {
            'Project' => $this->projectCode,
            'Budget line' => $this->budgetLineKey,
            'Fund source' => $this->fundSource?->value,
            'Contact' => $this->contact,
            'Center' => $this->center,
            default => null,
        };

        return $value === null || trim($value) === '' ? null : $value;
    }

    /** @return array<string, mixed> */
    public function toRow(int $lineNo, string $voucherId): array
    {
        return [
            'voucher_id' => $voucherId,
            'line_no' => $lineNo,
            'account_code' => $this->accountCode,
            'currency' => $this->currency,
            'debit_orig' => $this->debitOrig,
            'credit_orig' => $this->creditOrig,
            'rate' => $this->rate,
            'debit_usd' => $this->debitUsd,
            'credit_usd' => $this->creditUsd,
            'donor_currency' => $this->donorCurrency,
            'debit_donor' => $this->debitDonor,
            'credit_donor' => $this->creditDonor,
            'project_code' => $this->projectCode,
            'donor' => $this->donor,
            'budget_line_key' => $this->budgetLineKey,
            'fund_source' => $this->fundSource,
            'city' => $this->city,
            'center' => $this->center,
            'contact' => $this->contact,
            'restriction' => $this->restriction,
            'reason' => $this->reason,
            'lira_type' => $this->liraType,
        ];
    }
}
