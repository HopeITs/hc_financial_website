<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One order of a point-of-sale shift, like a restaurant's table (finance requests of 2026-11-26 and 2026-11-27): opened
 * on a table, take-away or delivery, items added over time, and paid when the table closes. While it is open its lines
 * carry their full price; once paid they carry their share after the discount, so the shift's voucher and the month's
 * totals are what was really taken.
 *
 * @property int $id
 * @property int $sheet_id
 * @property int $number the order's number within its shift (the invoice number)
 * @property string $status self::OPEN or self::PAID
 * @property string|null $table_label
 * @property string $service one of self::SERVICES
 * @property string|null $customer
 * @property int|null $persons
 * @property string $payment_method one of self::PAYMENTS
 * @property string|null $waiter
 * @property string|null $discount_percent
 * @property string $gross
 * @property string $discount
 * @property string $net
 * @property string|null $paid what the customer handed over, to show the change
 * @property string|null $note
 * @property int $created_by
 * @property Carbon|null $printed_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 */
class RevenueTicket extends Model
{
    public const OPEN = 'open';

    public const PAID = 'paid';

    /** In the hall (at a table), take-away, or delivered. */
    public const SERVICES = ['hall', 'takeaway', 'delivery'];

    /** Every sale is paid into the shift's cash box; "free" is a guest of the house (a full discount). */
    public const PAYMENTS = ['cash', 'free'];

    protected $table = 'gl_revenue_tickets';

    protected $fillable = [
        'sheet_id', 'number', 'status', 'table_label', 'service', 'customer', 'persons', 'payment_method', 'waiter',
        'discount_percent', 'gross', 'discount', 'net', 'paid', 'note', 'created_by', 'printed_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'persons' => 'integer',
            'discount_percent' => 'decimal:2',
            'gross' => 'decimal:2',
            'discount' => 'decimal:2',
            'net' => 'decimal:2',
            'paid' => 'decimal:2',
            'created_by' => 'integer',
            'printed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
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
