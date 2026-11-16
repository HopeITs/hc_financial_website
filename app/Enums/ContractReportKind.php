<?php

declare(strict_types=1);

namespace App\Enums;

/** What a report owed to the donor covers. */
enum ContractReportKind: string
{
    case Financial = 'financial';
    case Narrative = 'narrative';
    case Both = 'both';

    public function label(): string
    {
        return __('contracts.report_kinds.'.$this->value);
    }
}
