<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A closed month (YYYY-MM); nothing posts into it any more.
 *
 * @property string $month
 * @property Carbon $closed_at
 * @property int $closed_by
 */
class MonthClose extends Model
{
    protected $table = 'gl_month_closes';

    protected $primaryKey = 'month';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['month', 'closed_at', 'closed_by'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }
}
