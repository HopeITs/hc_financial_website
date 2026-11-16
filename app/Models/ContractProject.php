<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A project's share of a funding contract, as an amount in the contract currency.
 *
 * @property int $id
 * @property int $contract_id
 * @property string $project_code
 * @property string $amount
 */
class ContractProject extends Model
{
    protected $table = 'gl_contract_projects';

    protected $fillable = ['contract_id', 'project_code', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<GlProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(GlProject::class, 'project_code', 'code');
    }
}
