<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The month's revaluation voucher: the foreign cash valued again at the month's rates, the difference on 5503.
 *
 * @property int $id
 * @property string $month YYYY-MM
 * @property string|null $voucher_id
 * @property int $prepared_by
 */
class Revaluation extends Model
{
    protected $table = 'gl_revaluations';

    protected $fillable = ['month', 'voucher_id', 'prepared_by'];

    protected function casts(): array
    {
        return ['prepared_by' => 'integer'];
    }
}
