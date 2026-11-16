<?php

declare(strict_types=1);

namespace App\Enums;

/** The kind of a donor, as finance groups them. */
enum DonorType: string
{
    case ChurchAgency = 'church_agency';
    case Government = 'government';
    case Foundation = 'foundation';
    case Church = 'church';
    case Individual = 'individual';

    public function label(): string
    {
        return __('donors.types.'.$this->value);
    }
}
