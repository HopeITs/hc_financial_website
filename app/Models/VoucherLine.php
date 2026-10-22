<?php

namespace App\Models;

use App\Enums\FundSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One debit or credit line of a voucher, in its own currency and in USD (the books' currency).
 *
 * @property int $id
 * @property string $voucher_id
 * @property int $line_no
 * @property string $account_code
 * @property string $currency
 * @property string $debit_orig
 * @property string $credit_orig
 * @property string $rate
 * @property string $debit_usd
 * @property string $credit_usd
 * @property string|null $donor_currency
 * @property string $debit_donor
 * @property string $credit_donor
 * @property string|null $project_code
 * @property string|null $budget_line_key
 * @property FundSource|null $fund_source
 * @property string|null $eur_amount
 * @property string|null $eur_rate
 * @property string $commission_usd
 * @property bool $is_uncompensated
 */
class VoucherLine extends Model
{
    protected $table = 'gl_voucher_lines';

    protected $fillable = [
        'voucher_id', 'line_no', 'account_code', 'currency', 'debit_orig', 'credit_orig', 'rate', 'debit_usd', 'credit_usd',
        'donor_currency', 'debit_donor', 'credit_donor', 'project_code', 'donor', 'budget_line_key', 'fund_source', 'city',
        'center', 'contact', 'restriction', 'reason', 'lira_type', 'eur_amount', 'eur_rate', 'commission_usd', 'is_uncompensated',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'debit_orig' => 'decimal:2',
            'credit_orig' => 'decimal:2',
            'rate' => 'decimal:6',
            'debit_usd' => 'decimal:2',
            'credit_usd' => 'decimal:2',
            'debit_donor' => 'decimal:2',
            'credit_donor' => 'decimal:2',
            'fund_source' => FundSource::class,
            'eur_amount' => 'decimal:2',
            'eur_rate' => 'decimal:6',
            'commission_usd' => 'decimal:2',
            'is_uncompensated' => 'boolean',
        ];
    }

    /** @return BelongsTo<Voucher, $this> */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    /** @return BelongsTo<GlAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'account_code', 'code');
    }

    /** @return BelongsTo<GlProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(GlProject::class, 'project_code', 'code');
    }

    /** @return BelongsTo<GlBudgetLine, $this> */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(GlBudgetLine::class, 'budget_line_key', 'key');
    }
}
