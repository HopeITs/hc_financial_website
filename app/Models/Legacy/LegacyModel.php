<?php

namespace App\Models\Legacy;

use App\Models\Legacy\Concerns\IsReadOnly;
use Illuminate\Database\Eloquent\Model;

/**
 * A row of a table or view of the Windows program, read through the SELECT-only `legacy` connection.
 *
 * Columns keep the program's own (mostly PascalCase) names, and there are no timestamp columns to maintain.
 */
abstract class LegacyModel extends Model
{
    use IsReadOnly;

    protected $connection = 'legacy';

    public $timestamps = false;
}
