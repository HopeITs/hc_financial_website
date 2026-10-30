<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Who closed or reopened a month, when, and why a month was reopened (F-09).
 *
 * @property string $month
 * @property string $action closed or reopened
 * @property int $user_id
 * @property string|null $reason
 * @property Carbon $created_at
 */
class MonthCloseLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'gl_month_close_log';

    protected $fillable = ['month', 'action', 'user_id', 'reason'];
}
