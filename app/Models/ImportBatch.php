<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One run of the migration from the Windows program (type opening or operations) with its counts.
 *
 * @property int $id
 * @property string $type
 * @property string $status
 * @property int $total_count
 * @property int $posted_count
 * @property int $skipped_count
 * @property int $issue_count
 * @property int|null $started_by
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class ImportBatch extends Model
{
    protected $table = 'gl_import_batches';

    protected $fillable = [
        'type', 'status', 'total_count', 'posted_count', 'skipped_count', 'issue_count', 'started_by', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    /** @return HasMany<ImportIssue, $this> */
    public function issues(): HasMany
    {
        return $this->hasMany(ImportIssue::class, 'batch_id');
    }
}
