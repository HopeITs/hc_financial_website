<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The exchange rate of one currency on one day, for the whole organisation: proposed by an accountant, approved or
 * rejected by the Financial Manager. Approving a new rate for a day and currency replaces the one approved before.
 *
 * @property int $id
 * @property Carbon $day
 * @property string $currency
 * @property string $rate
 * @property string $status
 * @property string|null $note
 * @property int $proposed_by
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 */
class DailyRate extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const REPLACED = 'replaced';

    /** Taken back by whoever proposed it, or removed by the Financial Manager: kept in the history, never used. */
    public const REMOVED = 'removed';

    protected $table = 'gl_daily_rates';

    protected $fillable = ['day', 'currency', 'rate', 'status', 'note', 'proposed_by', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'rate' => 'decimal:6',
            'proposed_by' => 'integer',
            'decided_by' => 'integer',
            'decided_at' => 'datetime',
        ];
    }
}
