<?php

namespace App\Models;

use App\Enums\DonorType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Donor extends Model
{
    protected $table = 'gl_donors';

    protected $fillable = [
        'code', 'name_en', 'name_ar', 'type', 'country', 'currency',
        'contact_name', 'contact_email', 'contact_phone', 'notes', 'is_active',
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
}
