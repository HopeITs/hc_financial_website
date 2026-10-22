<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A legacy row the import could not turn into a voucher (unmapped name, missing rule, skipped, fallback rate).
 *
 * @property int $id
 * @property int $batch_id
 * @property int|null $legacy_action_id
 * @property string $kind
 * @property string $message
 * @property array<string, mixed>|null $payload
 */
class ImportIssue extends Model
{
    protected $table = 'gl_import_issues';

    protected $fillable = ['batch_id', 'legacy_action_id', 'kind', 'message', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    /** @return BelongsTo<ImportBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }
}
