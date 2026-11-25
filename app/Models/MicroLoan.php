<?php

namespace App\Models;

use App\Services\Projects\ProjectAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The loan and the grant a micro project gives one beneficiary, with its repayment schedule.
 *
 * @property int $id
 * @property string $beneficiary
 * @property string|null $national_number
 * @property string|null $mobile
 * @property string|null $partners
 * @property int $payment_interval_months
 * @property Carbon|null $first_payment_date
 * @property string|null $project_code none for a loan funded from an instalment fund
 * @property string $loan_amount
 * @property string $grant_amount
 * @property string $currency
 * @property int|null $legacy_micro_project_id
 * @property string|null $disbursement_voucher_id
 * @property string|null $funder the donor whose instalment fund it belongs to (finance decision of 2026-11-22)
 * @property string|null $city the city of the cash it was given from
 * @property string $stage the file's stage (finance decisions of 2026-11-23): see the STAGE constants
 * @property string|null $name_en
 * @property string|null $project_number
 * @property string|null $profession
 * @property string|null $address the place the equipment goes to
 * @property string|null $rate for a loan in pounds, the rate its dollar value was set at
 * @property string|null $requested_amount the amount the payment order asks for, in the loan's currency
 * @property string $seizure_fee
 * @property string $extra_amount
 * @property string|null $extra_reason
 * @property string $recovery_percent the part of the loan the beneficiary repays; the rest is the grant
 * @property string|null $instalment_amount
 * @property string|null $fund_source
 * @property list<array{role: int, user: int, name: string, at: string}>|null $approvals
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property string|null $return_reason
 * @property string|null $trust_bond_amount
 * @property Carbon|null $protocol_signed_on
 * @property Carbon|null $loan_date the date of the last invoice
 * @property string|null $book_usd what the repayable part is worth on 1204, in dollars
 * @property string|null $closing_voucher_id the closing's journal voucher: the repayable share from 5413 to 1204 (or, for invoices booked on 1204, the grant share the other way)
 * @property Carbon|null $imported_at when an old project was imported for follow-up (finance request of 2026-11-27); null for a loan opened in the program
 * @property string $legacy_paid what an imported loan's beneficiary had repaid before the import, in the loan's currency
 * @property int|null $created_by
 * @property Carbon $created_at
 */
class MicroLoan extends Model
{
    /** A new file: details, purchase list and quotes, no voucher. */
    public const DRAFT = 'draft';

    /** The payment order waits for its approvals. */
    public const SUBMITTED = 'submitted';

    /** Sent back to the preparer with a reason; it can be changed and submitted again. */
    public const RETURNED = 'returned';

    /** Approved: the purchases are made. */
    public const APPROVED = 'approved';

    /** The receipt protocol is signed: the loan is open and repaid. */
    public const ACTIVE = 'active';

    public const CANCELLED = 'cancelled';

    /** The stages a file is changed in. */
    public const EDITABLE = [self::DRAFT, self::RETURNED];

    protected $table = 'gl_micro_loans';

    protected $fillable = [
        'beneficiary', 'national_number', 'mobile', 'partners', 'project_code', 'loan_amount', 'grant_amount', 'currency',
        'payment_interval_months', 'first_payment_date', 'legacy_micro_project_id', 'disbursement_voucher_id', 'funder', 'city',
        'stage', 'name_en', 'project_number', 'profession', 'address', 'rate', 'requested_amount', 'seizure_fee', 'extra_amount',
        'extra_reason', 'recovery_percent', 'instalment_amount', 'fund_source', 'approvals', 'submitted_at', 'approved_at',
        'return_reason', 'trust_bond_amount', 'protocol_signed_on', 'loan_date', 'book_usd', 'closing_voucher_id', 'created_by',
        'imported_at', 'legacy_paid',
    ];

    protected function casts(): array
    {
        return [
            'payment_interval_months' => 'integer',
            'first_payment_date' => 'date',
            'loan_amount' => 'decimal:2',
            'grant_amount' => 'decimal:2',
            'legacy_micro_project_id' => 'integer',
            'rate' => 'decimal:6',
            'requested_amount' => 'decimal:2',
            'seizure_fee' => 'decimal:2',
            'extra_amount' => 'decimal:2',
            'recovery_percent' => 'decimal:2',
            'instalment_amount' => 'decimal:2',
            'approvals' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'trust_bond_amount' => 'decimal:2',
            'protocol_signed_on' => 'date',
            'loan_date' => 'date',
            'book_usd' => 'decimal:2',
            'imported_at' => 'datetime',
            'legacy_paid' => 'decimal:2',
            'created_by' => 'integer',
        ];
    }

    /**
     * Only the open loans, not the files still on their way.
     *
     * @param  Builder<MicroLoan>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('stage', self::ACTIVE);
    }

    /**
     * Only the files the person may see (role review of 2026-11-26): a project manager those of their own projects; a
     * fund's files belong to no project and stay with those who are not limited to projects.
     *
     * @param  Builder<MicroLoan>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $limit = app(ProjectAccess::class)->limitedTo($user);

        if ($limit !== null) {
            $query->whereIn('project_code', GlProject::query()->whereIn('legacy_account_id', $limit)->select('code'));
        }
    }

    /** @return HasMany<MicroItem, $this> the purchase list */
    public function items(): HasMany
    {
        return $this->hasMany(MicroItem::class, 'loan_id')->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<MicroQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(MicroQuote::class, 'loan_id')->orderBy('id');
    }

    /** @return HasMany<MicroPurchase, $this> the advances, invoices and returned advances, oldest first */
    public function purchases(): HasMany
    {
        return $this->hasMany(MicroPurchase::class, 'loan_id')->orderBy('purchase_date')->orderBy('id');
    }

    /** @return BelongsTo<GlProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(GlProject::class, 'project_code', 'code');
    }

    /** @return HasMany<MicroInstalment, $this> */
    public function instalments(): HasMany
    {
        return $this->hasMany(MicroInstalment::class, 'loan_id')->orderBy('due_date')->orderBy('id');
    }

    /** @return HasMany<MicroPayment, $this> the repayments and write-offs, oldest first */
    public function payments(): HasMany
    {
        return $this->hasMany(MicroPayment::class, 'loan_id')->orderBy('id');
    }

    /** @return BelongsTo<Voucher, $this> the payment voucher that gave the loan (none for loans opened before 2026-11-21) */
    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'disbursement_voucher_id')->withTrashed();
    }

    /** How the loan is shown: its project, or, for a loan from an instalment fund, its funder and city. */
    public function label(): string
    {
        return $this->project_code ?? trim($this->funder.' · '.$this->city, ' ·');
    }

    /**
     * What the beneficiary still owes: the instalments' amounts less what was paid and written off.
     *
     * @return numeric-string
     */
    public function owed(): string
    {
        return array_reduce($this->instalments->all(), fn (string $sum, MicroInstalment $instalment): string => bcadd($sum, $instalment->outstanding(), 2), '0.00');
    }
}
