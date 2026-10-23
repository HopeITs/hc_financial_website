<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One instalment of a micro loan. `paid_voucher_id` is the receipt voucher of the latest payment on it.
 *
 * @property int $id
 * @property int $loan_id
 * @property Carbon $due_date
 * @property string $amount
 * @property string $paid_amount
 * @property string|null $paid_voucher_id
 */
class MicroInstalment extends Model
{
    protected $table = 'gl_micro_instalments';

    protected $fillable = ['loan_id', 'due_date', 'amount', 'paid_amount', 'paid_voucher_id'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<MicroLoan, $this> */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(MicroLoan::class, 'loan_id');
    }

    /**
     * What is still owed on this instalment.
     *
     * @return numeric-string
     */
    public function outstanding(): string
    {
        return bcsub(Money::normalize($this->amount), Money::normalize($this->paid_amount), 2);
    }
}
