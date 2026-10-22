<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The SYP rate used to allocate pounds to projects of a city: proposed by an accountant, approved by the finance manager.
 *
 * @property int $id
 * @property string $city
 * @property string $rate
 * @property Carbon $effective_from
 * @property string $status
 * @property int $proposed_by
 * @property int|null $approved_by
 * @property bool $allocate_at_average_cost
 */
class AllocationRate extends Model
{
    protected $table = 'gl_allocation_rates';

    protected $fillable = [
        'city', 'rate', 'effective_from', 'status', 'proposed_by', 'approved_by', 'allocate_at_average_cost',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
            'effective_from' => 'date',
            'allocate_at_average_cost' => 'boolean',
        ];
    }
}
