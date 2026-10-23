<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a request is for when it needs price quotes: large maintenance, fixed assets and electronics need two or three
 * before it can be submitted. Routine requests have no category.
 */
enum RequestCategory: string
{
    case Maintenance = 'maintenance';
    case FixedAssets = 'fixed_assets';
    case Electronics = 'electronics';

    public const MIN_QUOTES = 2;

    public const MAX_QUOTES = 3;

    public function label(): string
    {
        return __('requests.category.'.$this->value);
    }
}
