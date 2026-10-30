<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry of a voucher's audit trail: what changed, the old and new value, who and why.
 *
 * @property int $id
 * @property string $voucher_id
 * @property string $action
 * @property string|null $field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property int $changed_by
 * @property string|null $reason
 * @property Carbon $created_at
 */
class VoucherAudit extends Model
{
    protected $table = 'gl_voucher_audit';

    public const UPDATED_AT = null;

    /** A Super User's action carries the `is_super` marker (F-06). */
    protected static function booted(): void
    {
        static::creating(function (self $audit): void {
            $user = auth()->user();

            if ($user instanceof User && $user->isSuperUser()) {
                $audit->setAttribute('is_super', true);
            }
        });
    }

    protected $fillable = ['voucher_id', 'action', 'field', 'old_value', 'new_value', 'changed_by', 'reason'];

    /** @return BelongsTo<Voucher, $this> */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }
}
