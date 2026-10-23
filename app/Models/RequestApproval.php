<?php

namespace App\Models;

use App\Enums\RoleId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A decision on a request: step 1 is the Financial Manager, step 2 the General Manager (above USD 2,000).
 *
 * @property int $id
 * @property int $request_id
 * @property int $step
 * @property RoleId $role_id
 * @property string $decision
 * @property int $decided_by
 * @property string|null $reason
 * @property Carbon $decided_at
 */
class RequestApproval extends Model
{
    public $timestamps = false;

    protected $table = 'gl_request_approvals';

    protected $fillable = ['request_id', 'step', 'role_id', 'decision', 'decided_by', 'reason', 'decided_at'];

    protected function casts(): array
    {
        return ['role_id' => RoleId::class, 'decided_at' => 'datetime', 'step' => 'integer'];
    }
}
