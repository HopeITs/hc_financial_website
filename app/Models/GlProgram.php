<?php

namespace App\Models;

use App\Enums\ProgramStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * A program created on the web. The Windows program's own programs live in its `project` table, which is read only.
 *
 * @property int $id
 * @property string $name_en
 * @property string $name_ar
 * @property string|null $description
 * @property ProgramStatus $status
 */
class GlProgram extends Model
{
    protected $table = 'gl_programs';

    protected $fillable = ['name_en', 'name_ar', 'description', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['status' => ProgramStatus::class];
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
