<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An account of the chart: a header groups accounts, a posting account takes voucher lines.
 *
 * @property int $id
 * @property string $code
 * @property string $name_en
 * @property string $name_ar
 * @property string $level Header or Posting
 * @property string|null $parent_code
 * @property string $class Assets, Liabilities, Net assets, Income or Expenses
 * @property string|null $statement_line
 * @property string $normal_side Debit or Credit
 * @property string|null $currency USD, SYP, Any or null on a header
 * @property string|null $city
 * @property bool $is_cash
 * @property string|null $tags Comma separated tags a line must carry
 * @property string|null $usage_note
 * @property bool $is_active
 */
class GlAccount extends Model
{
    public const HEADER = 'Header';

    public const POSTING = 'Posting';

    /** The five classes, in the order of the chart. */
    public const CLASSES = ['Assets', 'Liabilities', 'Net assets', 'Income', 'Expenses'];

    protected $table = 'gl_accounts';

    protected $fillable = [
        'code', 'name_en', 'name_ar', 'level', 'parent_code', 'class', 'statement_line', 'normal_side',
        'currency', 'city', 'is_cash', 'tags', 'usage_note', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_cash' => 'boolean', 'is_active' => 'boolean'];
    }

    public function isHeader(): bool
    {
        return $this->level === self::HEADER;
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }

    /**
     * @return list<string>
     */
    public function tagList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->tags))));
    }
}
