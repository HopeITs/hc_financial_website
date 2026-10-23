<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\VoucherSource;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\Legacy\TillType;
use App\Support\Money;
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
 * @property VoucherSource $source
 * @property int|null $legacy_action_id
 */
class Voucher extends Model implements Approvable
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
            'source' => VoucherSource::class,
            'date' => 'date',
            'approved_at' => 'datetime',
            'is_carry_forward' => 'boolean',
            'is_temporary' => 'boolean',
            'legacy_action_id' => 'integer',
            'request_id' => 'integer',
        ];
    }

    public function preparedBy(): int
    {
        return $this->prepared_by;
    }

    /** What an approver's limit is measured against: the voucher's USD total (the sum of its debits). */
    public function approvalAmount(): string
    {
        return Money::normalize($this->lines()->reorder()->sum('debit_usd'));
    }

    /** The limits are read in USD, so this is the id of the USD currency in the Windows program's `tilltypes`. */
    public function approvalCurrencyId(): ?int
    {
        $id = TillType::query()->where('Name', 'USD')->value('ID');

        return is_numeric($id) ? (int) $id : null;
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
