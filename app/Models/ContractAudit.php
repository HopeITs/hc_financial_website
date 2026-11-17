<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One entry of an agreement's audit trail: a budget line added, removed or imported, or the agreement deleted.
 *
 * @property int $id
 * @property int $contract_id
 * @property string $contract_number
 * @property string $action
 * @property string|null $project_code
 * @property string|null $budget_line_key
 * @property array<string, mixed>|null $details
 * @property bool $after_approval
 * @property int $changed_by
 * @property Carbon $created_at
 */
class ContractAudit extends Model
{
    public const BUDGET_LINE_ADDED = 'budget_line_added';

    public const BUDGET_LINE_REMOVED = 'budget_line_removed';

    public const BUDGET_IMPORTED = 'budget_imported';

    public const AGREEMENT_DELETED = 'agreement_deleted';

    public const RECEIPT_LINKED = 'receipt_linked';

    public const RECEIPT_UNLINKED = 'receipt_unlinked';

    /** The actions that change a budget, shown in the agreement's "Budget changes" panel. */
    public const BUDGET_ACTIONS = [self::BUDGET_LINE_ADDED, self::BUDGET_LINE_REMOVED, self::BUDGET_IMPORTED];

    protected $table = 'gl_contract_audit';

    public const UPDATED_AT = null;

    protected $fillable = ['contract_id', 'contract_number', 'action', 'project_code', 'budget_line_key', 'details', 'after_approval', 'changed_by'];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'after_approval' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
