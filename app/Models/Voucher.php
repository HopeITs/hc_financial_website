<?php

namespace App\Models;

use App\Contracts\Approvable;
use App\Enums\RoleId;
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

    /**
     * What an approver's limit is measured against: the voucher's USD total (the sum of its debits). A transfer has
     * two legs that each post a debit, so it is measured by its larger leg once: per account the larger of its debits
     * and credits, and the biggest of those.
     */
    public function approvalAmount(): string
    {
        if ($this->type !== VoucherType::Transfer) {
            return Money::normalize($this->lines()->reorder()->sum('debit_usd'));
        }

        $largest = Money::zero();

        foreach ($this->lines()->reorder()->get(['account_code', 'debit_usd', 'credit_usd'])->groupBy('account_code') as $legLines) {
            $debit = $legLines->reduce(fn (string $sum, VoucherLine $line) => bcadd($sum, Money::normalize($line->debit_usd), 2), Money::zero());
            $credit = $legLines->reduce(fn (string $sum, VoucherLine $line) => bcadd($sum, Money::normalize($line->credit_usd), 2), Money::zero());
            $leg = bccomp($debit, $credit, 2) >= 0 ? $debit : $credit;

            if (bccomp($leg, $largest, 2) > 0) {
                $largest = $leg;
            }
        }

        return $largest;
    }

    /** A donor receipt: a receipt (CRV) that debits the transfer company account (the hawala receipt). */
    public function isDonorReceipt(): bool
    {
        return $this->type === VoucherType::Receipt
            && $this->lines()->reorder()
                ->where('account_code', (string) config('ledger.transfer_company_account'))
                ->where('debit_usd', '>', 0)
                ->exists();
    }

    /**
     * The approval steps already done on a donor receipt, in order (kept in the audit trail until the last one).
     *
     * @return list<RoleId>
     */
    public function approvedSteps(): array
    {
        return array_values($this->audits()->where('action', 'approve_step')->orderBy('id')->get()
            ->map(fn (VoucherAudit $audit): ?RoleId => RoleId::tryFrom((int) $audit->new_value))
            ->filter()
            ->all());
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
