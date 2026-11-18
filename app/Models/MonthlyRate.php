<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The exchange rate of one currency for one month, typed by the Financial Manager: what invoices and expenses of
 * that month are booked at, unless an invoice is given another rate.
 *
 * @property int $id
 * @property string $month YYYY-MM
 * @property string $currency
 * @property string $rate
 * @property string|null $note
 * @property int $set_by
 */
class MonthlyRate extends Model
{
    protected $table = 'gl_monthly_rates';

    protected $fillable = ['month', 'currency', 'rate', 'note', 'set_by'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
            'set_by' => 'integer',
        ];
    }
}
