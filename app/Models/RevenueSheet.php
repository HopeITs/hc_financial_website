<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A project's takings of a day into a cash account (finance request of 2026-11-24), line by line; one receipt voucher
 * on 4002. A sheet whose voucher is rejected or deleted is `rejected` and counts no more.
 *
 * @property int $id
 * @property string $project_code
 * @property Carbon $day
 * @property string $cash_account
 * @property string $currency
 * @property string $total
 * @property string $status self::OPEN, self::POSTED or self::REJECTED
 * @property string|null $voucher_id
 * @property string|null $notes
 * @property int $created_by
 */
class RevenueSheet extends Model
{
    /** A point-of-sale shift still taking sales; closing it makes the voucher (finance request of 2026-11-26). */
    public const OPEN = 'open';

    public const POSTED = 'posted';

    public const REJECTED = 'rejected';

    protected $table = 'gl_revenue_sheets';

    protected $fillable = ['project_code', 'day', 'cash_account', 'currency', 'total', 'status', 'voucher_id', 'notes', 'created_by'];

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'total' => 'decimal:2',
            'created_by' => 'integer',
        ];
    }

    /** @return HasMany<RevenueLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(RevenueLine::class, 'sheet_id')->orderBy('id');
    }

    /** @return HasMany<RevenueTicket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(RevenueTicket::class, 'sheet_id')->orderByDesc('number');
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }
}
