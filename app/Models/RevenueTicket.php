<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One sale of a point-of-sale shift (finance request of 2026-11-26): what it came to, the discount given and what was
 * paid. Its lines carry the amounts after the discount, so the shift's voucher and the month's totals are net.
 *
 * @property int $id
 * @property int $sheet_id
 * @property int $number the sale's number within its shift
 * @property string $gross
 * @property string $discount
 * @property string $net
 * @property string|null $paid what the customer handed over, to show the change
 * @property string|null $note
 * @property int $created_by
 */
class RevenueTicket extends Model
{
    protected $table = 'gl_revenue_tickets';

    protected $fillable = ['sheet_id', 'number', 'gross', 'discount', 'net', 'paid', 'note', 'created_by'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'gross' => 'decimal:2',
            'discount' => 'decimal:2',
            'net' => 'decimal:2',
            'paid' => 'decimal:2',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<RevenueSheet, $this> */
    public function sheet(): BelongsTo
    {
        return $this->belongsTo(RevenueSheet::class, 'sheet_id');
    }

    /** @return HasMany<RevenueLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(RevenueLine::class, 'ticket_id')->orderBy('id');
    }
}
