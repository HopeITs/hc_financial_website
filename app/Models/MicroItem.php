<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a micro-project file's purchase list (طلب مشتريات): what is bought, its kind, how many, new or used.
 *
 * @property int $id
 * @property int $loan_id
 * @property string $name
 * @property string|null $kind
 * @property string $quantity
 * @property string $condition self::NEW or self::USED
 * @property int $sort
 */
class MicroItem extends Model
{
    public const NEW = 'new';

    public const USED = 'used';

    protected $table = 'gl_micro_items';

    protected $fillable = ['loan_id', 'name', 'kind', 'quantity', 'condition', 'sort'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'sort' => 'integer',
        ];
    }

    /** @return BelongsTo<MicroLoan, $this> */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(MicroLoan::class, 'loan_id');
    }
}
