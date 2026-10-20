<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The interface languages: the value is the Laravel locale and the lang/ directory name.
 */
enum Locale: string
{
    case English = 'en';
    case Arabic = 'ar';

    /**
     * The text direction for the <html dir> attribute.
     */
    public function direction(): string
    {
        return $this === self::Arabic ? 'rtl' : 'ltr';
    }

    /**
     * The language currently applied to the request.
     */
    public static function current(): self
    {
        return self::tryFrom(app()->getLocale()) ?? self::English;
    }
}
