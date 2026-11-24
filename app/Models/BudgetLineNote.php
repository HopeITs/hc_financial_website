<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Why a budget line went over or stayed under its budget, written for one reporting period of the donor's financial
 * report (finance request of 2026-11-26).
 *
 * @property int $id
 * @property string $budget_line_key
 * @property Carbon $period_from
 * @property Carbon $period_to
 * @property string $note
 * @property int $written_by
 */
class BudgetLineNote extends Model
{
    protected $table = 'gl_budget_line_notes';

    protected $fillable = ['budget_line_key', 'period_from', 'period_to', 'note', 'written_by'];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'written_by' => 'integer',
        ];
    }

    /** @return BelongsTo<GlBudgetLine, $this> */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(GlBudgetLine::class, 'budget_line_key', 'key');
    }
}
