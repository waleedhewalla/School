<?php

namespace App\Support;

class Locale
{
    /** @return list<string> */
    public static function supported(): array
    {
        return config('madrasa.locales');
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::supported(), true);
    }

    public static function direction(?string $locale = null): string
    {
        return in_array($locale ?? app()->getLocale(), config('madrasa.rtl_locales'), true) ? 'rtl' : 'ltr';
    }
}
