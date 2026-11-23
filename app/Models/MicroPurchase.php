<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A money movement of a micro-project file's purchases, with its voucher:
 *
 * - an advance to the purchasing officer (1201), in the cash's currency;
 * - an invoice, paid directly to the merchant from the cash, or from an advance (`advance_id`): it adds its value to
 *   the loan (1204), in the loan's currency (`loan_amount`);
 * - what is left of an advance, given back to the cash.
 *
 * @property int $id
 * @property int $loan_id
 * @property string $kind self::ADVANCE, self::INVOICE or self::RETURN
 * @property int|null $advance_id
 * @property string|null $person the purchasing officer of an advance
 * @property string|null $cash_account
 * @property bool $is_seizure the judicial seizure fee of a loan of 3,000 USD or more
 * @property string|null $merchant
 * @property string|null $number
 * @property Carbon $purchase_date
 * @property string|null $description_ar
 * @property string|null $description_en
 * @property string $currency
 * @property string $amount
 * @property string $rate
 * @property string $loan_amount
 * @property string $usd
 * @property string $voucher_id
 * @property string|null $original_name
 * @property string|null $stored_path
 * @property int $created_by
 */
class MicroPurchase extends Model
{
    public const ADVANCE = 'advance';

    public const INVOICE = 'invoice';

    public const RETURN = 'return';

    protected $table = 'gl_micro_purchases';

    protected $fillable = [
        'loan_id', 'kind', 'advance_id', 'person', 'cash_account', 'is_seizure', 'merchant', 'number', 'purchase_date', 'description_ar',
        'description_en', 'currency', 'amount', 'rate', 'loan_amount', 'usd', 'voucher_id', 'original_name', 'stored_path', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_seizure' => 'boolean',
            'purchase_date' => 'date',
            'amount' => 'decimal:2',
            'rate' => 'decimal:6',
            'loan_amount' => 'decimal:2',
            'usd' => 'decimal:2',
            'advance_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<MicroLoan, $this> */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(MicroLoan::class, 'loan_id');
    }

    /** @return BelongsTo<MicroPurchase, $this> the advance an invoice or a return belongs to */
    public function advance(): BelongsTo
    {
        return $this->belongsTo(self::class, 'advance_id');
    }

    /** @return BelongsTo<Voucher, $this> */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id')->withTrashed();
    }
}
