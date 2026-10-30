<?php

declare(strict_types=1);

namespace App\Enums;

enum RequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Returned = 'returned';
    case Closed = 'closed';

    public function label(): string
    {
        return __('requests.status.'.$this->value);
    }
}
