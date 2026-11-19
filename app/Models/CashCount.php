<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One day's count of a cash or bank account against its book balance, and the voucher that books the difference on
 * 5505 cash shortages and overages.
 *
 * @property int $id
 * @property Carbon $day
 * @property string $account_code
 * @property string $book
 * @property string $counted
 * @property string $difference
 * @property string|null $explanation
 * @property string|null $voucher_id
 * @property int $counted_by
 */
class CashCount extends Model
{
    protected $table = 'gl_cash_counts';

    protected $fillable = ['day', 'account_code', 'book', 'counted', 'difference', 'explanation', 'voucher_id', 'counted_by'];

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'book' => 'decimal:2',
            'counted' => 'decimal:2',
            'difference' => 'decimal:2',
            'counted_by' => 'integer',
        ];
    }
}
