<?php

namespace App\Models;

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A ledger voucher such as CRV-2026-0001. `prepared_by` and `approved_by` hold the legacy `user`.`ID`.
 *
 * @property string $id
 * @property VoucherType $type
 * @property string|null $transfer_kind
 * @property int|null $request_id
 * @property Carbon $date
 * @property string|null $description
 * @property VoucherStatus $status
 * @property int $prepared_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property bool $is_carry_forward
 * @property bool $is_temporary
 * @property string $source
 * @property int|null $legacy_action_id
 */
class Voucher extends Model
{
    use SoftDeletes;

    protected $table = 'gl_vouchers';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'type', 'transfer_kind', 'request_id', 'date', 'description', 'status', 'prepared_by', 'approved_by', 'approved_at',
        'is_carry_forward', 'is_temporary', 'source', 'legacy_action_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => VoucherType::class,
            'status' => VoucherStatus::class,
            'date' => 'date',
            'approved_at' => 'datetime',
            'is_carry_forward' => 'boolean',
            'is_temporary' => 'boolean',
            'legacy_action_id' => 'integer',
            'request_id' => 'integer',
        ];
    }

    /** @return HasMany<VoucherLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(VoucherLine::class, 'voucher_id')->orderBy('line_no');
    }

    /** @return HasMany<VoucherAudit, $this> */
    public function audits(): HasMany
    {
        return $this->hasMany(VoucherAudit::class, 'voucher_id');
    }

    /** @return HasMany<VoucherAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(VoucherAttachment::class, 'voucher_id');
    }
}
