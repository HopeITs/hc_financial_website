<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The last number given for a voucher type in a year; numbers are never reused.
 *
 * @property string $type
 * @property int $year
 * @property int $last_number
 */
class VoucherSequence extends Model
{
    protected $table = 'gl_voucher_sequences';

    public $incrementing = false;

    protected $fillable = ['type', 'year', 'last_number'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'last_number' => 'integer'];
    }
}
