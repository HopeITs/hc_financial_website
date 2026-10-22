<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A project manager's request for money (`gl_requests`); a voucher links to it through `request_id`.
 *
 * @property int $id
 * @property string $type
 * @property string $project_code
 * @property string $amount
 * @property string $currency
 * @property string|null $description
 * @property string $status
 * @property int $requested_by
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 */
class LedgerRequest extends Model
{
    public const APPROVED = 'approved';

    protected $table = 'gl_requests';

    protected $fillable = [
        'type', 'project_code', 'amount', 'currency', 'description', 'status', 'requested_by', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'decided_at' => 'datetime'];
    }
}
