<?php

namespace App\Models;

use App\Enums\ContractClosingOutcome;
use App\Enums\ContractRestriction;
use App\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A funding contract: one donor, one currency, split over one or more projects by amount. It is usable in entries
 * and reports only once the Financial Manager has approved it. Closing records what happens to the unspent balance
 * and needs the Financial Manager, then the General Manager.
 *
 * @property int $id
 * @property string $number
 * @property string|null $donor_reference
 * @property int $donor_id
 * @property string $title
 * @property Carbon|null $signed_on
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string $currency
 * @property string $amount
 * @property string $fixed_rate
 * @property ContractRestriction $restriction
 * @property bool $is_yearly_general
 * @property ContractStatus $status
 * @property string|null $signed_file_path
 * @property string|null $signed_file_name
 * @property string|null $notes
 * @property int $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property ContractClosingOutcome|null $closing_outcome
 * @property string|null $closing_note
 * @property string|null $closing_file_path
 * @property string|null $closing_file_name
 * @property int|null $closing_requested_by
 * @property Carbon|null $closing_requested_at
 * @property int|null $closing_fm_by
 * @property Carbon|null $closing_fm_at
 * @property int|null $closing_gm_by
 * @property Carbon|null $closing_gm_at
 */
class Contract extends Model
{
    protected $table = 'gl_contracts';

    protected $fillable = [
        'number', 'donor_reference', 'donor_id', 'title', 'signed_on', 'starts_on', 'ends_on', 'currency', 'amount',
        'fixed_rate', 'restriction', 'is_yearly_general', 'status', 'signed_file_path', 'signed_file_name', 'notes',
        'created_by', 'approved_by', 'approved_at', 'closing_outcome', 'closing_note', 'closing_file_path',
        'closing_file_name', 'closing_requested_by', 'closing_requested_at', 'closing_fm_by', 'closing_fm_at',
        'closing_gm_by', 'closing_gm_at',
    ];

    protected function casts(): array
    {
        return [
            'signed_on' => 'date',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'amount' => 'decimal:2',
            'fixed_rate' => 'decimal:6',
            'restriction' => ContractRestriction::class,
            'is_yearly_general' => 'boolean',
            'status' => ContractStatus::class,
            'approved_at' => 'datetime',
            'closing_outcome' => ContractClosingOutcome::class,
            'closing_requested_at' => 'datetime',
            'closing_fm_at' => 'datetime',
            'closing_gm_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Donor, $this> */
    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    /** @return HasMany<ContractProject, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(ContractProject::class)->orderBy('project_code');
    }

    /** @return HasMany<ContractInstalment, $this> */
    public function instalments(): HasMany
    {
        return $this->hasMany(ContractInstalment::class)->orderBy('number');
    }

    /** @return HasMany<ContractReceipt, $this> the receipt vouchers linked to its instalments */
    public function receipts(): HasMany
    {
        return $this->hasMany(ContractReceipt::class);
    }

    /** @return HasMany<ContractReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(ContractReport::class)->orderBy('due_on');
    }

    /**
     * The contracts a person may see: all of them, or, for someone limited to some projects (a project manager),
     * only those that cover one of their projects.
     *
     * @param  Builder<Contract>  $query
     * @param  list<string>|null  $projectCodes  null when the person is not limited
     */
    public function scopeVisibleTo(Builder $query, ?array $projectCodes): void
    {
        if ($projectCodes === null) {
            return;
        }

        $query->whereHas('projects', fn (Builder $share) => $share->whereIn('project_code', $projectCodes));
    }

    /** The next contract number of a year: CON-2026-001, CON-2026-002 ... */
    public static function nextNumber(int $year): string
    {
        $prefix = 'CON-'.$year.'-';
        $last = self::query()->where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');
        $next = $last === null ? 1 : ((int) substr((string) $last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /** Ended and cancelled contracts take no new spending or changes. */
    public function isOpen(): bool
    {
        return ! in_array($this->status, [ContractStatus::Ended, ContractStatus::Cancelled], true);
    }

    public function isClosingRequested(): bool
    {
        return $this->closing_requested_at !== null && $this->status !== ContractStatus::Ended;
    }
}
