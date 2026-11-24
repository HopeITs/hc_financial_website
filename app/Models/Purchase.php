<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A purchase file (finance request of 2026-11-24): one purchase of an approved request, from the suppliers' quotes to
 * the payment, on the forms of the organisation's procurement process. Its number is its id: the RFQ, the bid
 * analysis, the PO, the GRN or SRN and the RDP carry it.
 *
 * @property int $id
 * @property int $request_id
 * @property string $project_code
 * @property string $stage self::SOURCING, SUBMITTED, ORDERED, RECEIVED, PAID or CANCELLED
 * @property string $kind self::GOODS or self::SERVICE
 * @property string $title
 * @property string|null $location
 * @property string|null $delivery_place
 * @property Carbon|null $needed_by
 * @property string $currency
 * @property int|null $awarded_quote_id
 * @property string|null $award_reason
 * @property Carbon|null $ordered_on
 * @property string $other_charges
 * @property list<array{role: int, user: int, name: string, at: string}>|null $approvals
 * @property Carbon|null $approved_at
 * @property string|null $return_reason
 * @property Carbon|null $received_on
 * @property string|null $receiving_location
 * @property string|null $invoice_number
 * @property Carbon|null $invoice_date
 * @property string|null $invoice_amount
 * @property bool|null $accepted
 * @property string|null $receipt_remarks
 * @property string|null $payment_method self::CASH or self::ADVANCE
 * @property string|null $voucher_id
 * @property int $created_by
 */
class Purchase extends Model
{
    /** Quotes are gathered and compared, and a supplier is chosen. */
    public const SOURCING = 'sourcing';

    /** The purchase order waits for its approvals. */
    public const SUBMITTED = 'submitted';

    /** The purchase order is approved and sent to the supplier. */
    public const ORDERED = 'ordered';

    /** The goods or the service came, with the supplier's invoice. */
    public const RECEIVED = 'received';

    /** The payment voucher is made. */
    public const PAID = 'paid';

    public const CANCELLED = 'cancelled';

    /** The steps of a file, in order. */
    public const STAGES = [self::SOURCING, self::SUBMITTED, self::ORDERED, self::RECEIVED, self::PAID];

    public const GOODS = 'goods';

    public const SERVICE = 'service';

    public const CASH = 'cash';

    public const ADVANCE = 'advance';

    protected $table = 'gl_purchases';

    protected $fillable = [
        'request_id', 'project_code', 'stage', 'kind', 'title', 'location', 'delivery_place', 'needed_by', 'currency',
        'awarded_quote_id', 'award_reason', 'ordered_on', 'other_charges', 'approvals', 'approved_at', 'return_reason',
        'received_on', 'receiving_location', 'invoice_number', 'invoice_date', 'invoice_amount', 'accepted', 'receipt_remarks',
        'payment_method', 'voucher_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'needed_by' => 'date',
            'ordered_on' => 'date',
            'other_charges' => 'decimal:2',
            'approvals' => 'array',
            'approved_at' => 'datetime',
            'received_on' => 'date',
            'invoice_date' => 'date',
            'invoice_amount' => 'decimal:2',
            'accepted' => 'boolean',
            'awarded_quote_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /** The number printed on a form of the file: `PO-0012`. */
    public function number(string $form): string
    {
        return $form.'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** @return BelongsTo<LedgerRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(LedgerRequest::class, 'request_id');
    }

    /** @return BelongsTo<GlProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(GlProject::class, 'project_code', 'code');
    }

    /** @return HasMany<PurchaseItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'purchase_id')->orderBy('line_no');
    }

    /** @return HasMany<PurchaseQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(PurchaseQuote::class, 'purchase_id')->orderBy('id');
    }

    /** @return BelongsTo<PurchaseQuote, $this> */
    public function awardedQuote(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuote::class, 'awarded_quote_id');
    }

    /** @return BelongsTo<Voucher, $this> */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id')->withTrashed();
    }
}
