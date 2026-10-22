<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A file attached to a voucher, kept on the private disk. `legacy_attachment_id` is `action_attachments`.`id`.
 *
 * @property int $id
 * @property string $voucher_id
 * @property string $original_name
 * @property string $stored_path
 * @property string|null $mime
 * @property int $size
 * @property string|null $sha256
 * @property int|null $legacy_attachment_id
 * @property int|null $uploaded_by
 */
class VoucherAttachment extends Model
{
    use SoftDeletes;

    protected $table = 'gl_voucher_attachments';

    protected $fillable = [
        'voucher_id', 'original_name', 'stored_path', 'mime', 'size', 'sha256', 'legacy_attachment_id', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer', 'legacy_attachment_id' => 'integer'];
    }

    /** @return BelongsTo<Voucher, $this> */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }
}
