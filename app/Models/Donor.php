<?php

namespace App\Models;

use App\Enums\DonorType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A donor, kept in its own list apart from the chart of accounts (all income goes to 4001 with the donor on the line).
 *
 * @property int $id
 * @property string $code
 * @property string $name_en
 * @property string $name_ar
 * @property DonorType $type
 * @property string|null $country
 * @property string|null $currency
 * @property string|null $contact_name
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $notes
 * @property bool $is_active
 * @property string|null $logo_path the logo on the private disk (finance request of 2026-11-28)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Donor extends Model
{
    protected $table = 'gl_donors';

    protected $fillable = [
        'code', 'name_en', 'name_ar', 'type', 'country', 'currency',
        'contact_name', 'contact_email', 'contact_phone', 'notes', 'is_active', 'logo_path',
    ];

    protected function casts(): array
    {
        return [
            'type' => DonorType::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Contract, $this> */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * @param  Builder<Donor>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }

    /**
     * The donor a project or a fund names (`gl_projects.donor`, `gl_micro_loans.funder`), by its English or Arabic
     * name or its code; null when no donor in the list answers to it.
     */
    public static function named(?string $name): ?self
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $exact = self::query()->where(fn (Builder $query) => $query->where('name_en', $name)->orWhere('name_ar', $name)->orWhere('code', $name))
            ->orderByDesc('is_active')->first();

        if ($exact !== null) {
            return $exact;
        }

        // The name on a project is often shorter or longer than the donor's ("Caritas" for "Caritas Poland"): one donor
        // whose name holds it, or is held in it, is the one meant.
        $wanted = mb_strtolower($name);
        $close = self::query()->get()->filter(function (self $donor) use ($wanted): bool {
            foreach ([$donor->name_en, $donor->name_ar, $donor->code] as $candidate) {
                $candidate = mb_strtolower(trim((string) $candidate));
                if ($candidate !== '' && mb_strlen($candidate) >= 3 && (str_contains($candidate, $wanted) || str_contains($wanted, $candidate))) {
                    return true;
                }
            }

            return false;
        });

        return $close->count() === 1 ? $close->first() : null;
    }

    /**
     * The donor of a project's papers (finance request of 2026-11-28): the donor of the project's latest agreement, else
     * the one its donor name points to; for a loan from an instalment fund, the fund's funder.
     */
    public static function forProject(?GlProject $project, ?string $funder = null): ?self
    {
        if ($project !== null) {
            $donorId = ContractProject::query()->where('project_code', $project->code)
                ->join('gl_contracts as c', 'c.id', '=', 'gl_contract_projects.contract_id')
                ->orderByDesc('c.id')->value('c.donor_id');

            if ($donorId !== null && ($donor = self::query()->find($donorId)) !== null) {
                return $donor;
            }
        }

        return self::named($project?->donor ?? $funder);
    }

    /** The logo's file on the server, or null when the donor has none. */
    public function logoFile(): ?string
    {
        if ($this->logo_path === null || $this->logo_path === '' || ! Storage::disk('local')->exists($this->logo_path)) {
            return null;
        }

        return Storage::disk('local')->path($this->logo_path);
    }

    /** Where a page shows the logo, or null when the donor has none. */
    public function logoUrl(): ?string
    {
        return $this->logoFile() !== null ? route('funding.donors.logo', ['donor' => $this->id, 'v' => substr(md5((string) $this->logo_path), 0, 8)]) : null;
    }
}
