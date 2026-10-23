<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A price quote attached to a request, kept on the private disk.
 *
 * @property int $id
 * @property int $request_id
 * @property string $original_name
 * @property string $stored_path
 * @property string|null $mime
 * @property int $size
 * @property string|null $sha256
 * @property int $uploaded_by
 */
class RequestQuote extends Model
{
    protected $table = 'gl_request_quotes';

    protected $fillable = ['request_id', 'original_name', 'stored_path', 'mime', 'size', 'sha256', 'uploaded_by'];
}
