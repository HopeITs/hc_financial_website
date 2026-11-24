<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a one-off request is for: large maintenance, fixed assets or electronics. Routine requests have no category. The
 * price quotes are gathered in the purchase file once the request is approved (finance decision of 2026-11-24); up to
 * MAX_QUOTES can still be attached to a request for information.
 */
enum RequestCategory: string
{
    case Maintenance = 'maintenance';
    case FixedAssets = 'fixed_assets';
    case Electronics = 'electronics';

    public const MAX_QUOTES = 3;

    public function label(): string
    {
        return __('requests.category.'.$this->value);
    }
}
