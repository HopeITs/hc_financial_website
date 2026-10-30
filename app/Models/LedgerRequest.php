<?php

namespace App\Models;

use App\Enums\RequestCategory;
use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A project manager's request for money (`gl_requests`); a voucher links to it through `request_id`.
 * `type` is `monthly` (the routine expenses of a month) or `extra` (an amount during the month); `amount` is the
 * total in USD of its lines.
 *
 * @property int $id
 * @property string $type
 * @property string $project_code
 * @property Carbon|null $month
 * @property string|null $category
 * @property string $amount
 * @property string $currency
 * @property string|null $description
 * @property string $status
 * @property int $requested_by
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $closed_at
 * @property string|null $reject_reason
 * @property string|null $return_reason
 */
class LedgerRequest extends Model
{
    public const APPROVED = 'approved';

    public const MONTHLY = 'monthly';

    public const EXTRA = 'extra';

    protected $table = 'gl_requests';

    protected $fillable = [
        'type', 'project_code', 'month', 'category', 'amount', 'currency', 'description', 'status', 'requested_by', 'decided_by',
        'decided_at', 'submitted_at', 'closed_at', 'reject_reason', 'return_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'month' => 'date',
            'decided_at' => 'datetime',
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** The category that needs price quotes, or null for a routine request. */
    public function categoryType(): ?RequestCategory
    {
        return $this->category === null ? null : RequestCategory::tryFrom($this->category);
    }

    public function state(): ?RequestStatus
    {
        return RequestStatus::tryFrom($this->status);
    }

    /** @return BelongsTo<GlProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(GlProject::class, 'project_code', 'code');
    }

    /** @return HasMany<RequestLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(RequestLine::class, 'request_id')->orderBy('id');
    }

    /** @return HasMany<RequestApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(RequestApproval::class, 'request_id')->orderBy('step');
    }

    /** @return HasMany<RequestAudit, $this> */
    public function audits(): HasMany
    {
        return $this->hasMany(RequestAudit::class, 'request_id')->orderBy('id');
    }

    /** @return HasMany<RequestQuote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(RequestQuote::class, 'request_id')->orderBy('id');
    }
}
