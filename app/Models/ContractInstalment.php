<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An instalment the donor is expected to send under a contract. A conditional one (for example "after the interim
 * report is accepted") becomes income only on the day its condition is met.
 *
 * @property int $id
 * @property int $contract_id
 * @property int $number
 * @property Carbon $due_on
 * @property string $amount
 * @property string|null $condition
 * @property bool $is_conditional
 * @property Carbon|null $condition_met_on
 * @property string|null $recognition_voucher_id the journal voucher that books it as income
 */
class ContractInstalment extends Model
{
    protected $table = 'gl_contract_instalments';

    protected $fillable = ['contract_id', 'number', 'due_on', 'amount', 'condition', 'is_conditional', 'condition_met_on', 'recognition_voucher_id'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'amount' => 'decimal:2',
            'is_conditional' => 'boolean',
            'condition_met_on' => 'date',
        ];
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return HasMany<ContractReceipt, $this> the receipt vouchers that brought it */
    public function receipts(): HasMany
    {
        return $this->hasMany(ContractReceipt::class, 'instalment_id');
    }

    /** @return BelongsTo<Voucher, $this> */
    public function recognitionVoucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'recognition_voucher_id');
    }

    /** A conditional instalment is income once its condition is met; any other is income from the signing. */
    public function isRecognised(): bool
    {
        return ! $this->is_conditional || $this->condition_met_on !== null;
    }

    /** Days past its due date, or 0 when it is not due yet. Receipts are not linked yet, so this reads the schedule alone. */
    public function daysLate(CarbonInterface $today): int
    {
        return $this->due_on->lt($today->startOfDay()) ? (int) $this->due_on->diffInDays($today->startOfDay()) : 0;
    }
}
