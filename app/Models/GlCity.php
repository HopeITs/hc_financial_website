<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A city that chart accounts (cash accounts above all) and operations are linked to.
 *
 * @property int $id
 * @property string $name
 * @property string|null $name_ar
 * @property bool $is_active
 */
class GlCity extends Model
{
    protected $table = 'gl_cities';

    protected $fillable = ['name', 'name_ar', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'ar' && $this->name_ar ? $this->name_ar : $this->name;
    }

    /** @return list<string> the names of the active cities, in order */
    public static function activeNames(): array
    {
        return array_values(self::query()->where('is_active', true)->orderBy('name')->pluck('name')->all());
    }
}
