<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A price quote for a micro-project file: the beneficiary's own, or one from a merchant the centre works with. The
 * chosen one is the basis of the payment order.
 *
 * @property int $id
 * @property int $loan_id
 * @property string $supplier
 * @property string $source self::BENEFICIARY or self::MERCHANT
 * @property Carbon|null $quoted_on
 * @property string $amount
 * @property string $currency
 * @property bool $chosen
 * @property string|null $original_name
 * @property string|null $stored_path
 * @property int $created_by
 */
class MicroQuote extends Model
{
    public const BENEFICIARY = 'beneficiary';

    public const MERCHANT = 'merchant';

    /** A file needs at least this many quotes before its payment order is submitted (finance decision of 2026-11-23). */
    public const MIN_QUOTES = 2;

    protected $table = 'gl_micro_quotes';

    protected $fillable = ['loan_id', 'supplier', 'source', 'quoted_on', 'amount', 'currency', 'chosen', 'original_name', 'stored_path', 'created_by'];

    protected function casts(): array
    {
        return [
            'quoted_on' => 'date',
            'amount' => 'decimal:2',
            'chosen' => 'boolean',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<MicroLoan, $this> */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(MicroLoan::class, 'loan_id');
    }
}
