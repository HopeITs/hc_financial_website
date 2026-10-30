<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What was counted or read from a statement for a cash account at the end of a month (F-09).
 *
 * @property string $month
 * @property string $account_code
 * @property numeric-string $counted
 * @property string|null $explanation
 * @property int $entered_by
 */
class MonthCount extends Model
{
    protected $table = 'gl_month_counts';

    protected $fillable = ['month', 'account_code', 'counted', 'explanation', 'entered_by'];
}
