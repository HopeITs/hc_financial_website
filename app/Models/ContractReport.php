<?php

namespace App\Models;

use App\Enums\ContractReportKind;
use App\Enums\ContractReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A report CHC owes the donor under a contract: financial, narrative or both, for a period, by a due date. It may hold
 * back an instalment until the donor accepts it.
 *
 * @property int $id
 * @property int $contract_id
 * @property ContractReportKind $kind
 * @property Carbon $period_from
 * @property Carbon $period_to
 * @property Carbon $due_on
 * @property ContractReportStatus $status
 * @property Carbon|null $sent_on
 * @property string|null $file_path
 * @property string|null $file_name
 * @property int|null $instalment_id
 */
class ContractReport extends Model
{
    /** Days before the due date when the reminder is due (finance decision 2026-11-16). */
    public const REMINDER_DAYS = 14;

    protected $table = 'gl_contract_reports';

    protected $fillable = ['contract_id', 'kind', 'period_from', 'period_to', 'due_on', 'status', 'sent_on', 'file_path', 'file_name', 'instalment_id'];

    protected function casts(): array
    {
        return [
            'kind' => ContractReportKind::class,
            'status' => ContractReportStatus::class,
            'period_from' => 'date',
            'period_to' => 'date',
            'due_on' => 'date',
            'sent_on' => 'date',
        ];
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<ContractInstalment, $this> */
    public function instalment(): BelongsTo
    {
        return $this->belongsTo(ContractInstalment::class, 'instalment_id');
    }
}
