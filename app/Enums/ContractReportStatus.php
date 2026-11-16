<?php

declare(strict_types=1);

namespace App\Enums;

/** Where a report owed to the donor stands. */
enum ContractReportStatus: string
{
    case NotSent = 'not_sent';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case ChangesRequested = 'changes_requested';

    public function label(): string
    {
        return __('contracts.report_statuses.'.$this->value);
    }
}
