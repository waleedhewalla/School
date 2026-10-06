<?php

namespace App\Support\Dates;

use App\Enums\DateDisplay;
use DateTimeInterface;
use IntlDateFormatter;

/**
 * Formats dates in the Gregorian calendar, the Umm al-Qura Hijri calendar
 * (the official Saudi calendar), or both. Uses PHP's intl extension, so no
 * conversion tables are kept in the app.
 */
class SchoolDate
{
    public static function gregorian(DateTimeInterface $date, ?string $locale = null, string $pattern = 'd MMMM y'): string
    {
        return self::format($date, 'gregorian', $locale, $pattern);
    }

    public static function hijri(DateTimeInterface $date, ?string $locale = null, string $pattern = 'd MMMM y'): string
    {
        $formatted = self::format($date, 'islamic-umalqura', $locale, $pattern);
        $suffix = self::locale($locale) === 'ar' ? 'هـ' : 'AH';

        return $formatted.' '.$suffix;
    }

    public static function display(DateTimeInterface $date, DateDisplay $mode, ?string $locale = null): string
    {
        return match ($mode) {
            DateDisplay::Gregorian => self::gregorian($date, $locale),
            DateDisplay::Hijri => self::hijri($date, $locale),
            DateDisplay::Both => self::hijri($date, $locale).' — '.self::gregorian($date, $locale),
        };
    }

    /**
     * Hijri year, month and day of a date (Umm al-Qura), e.g. for grouping
     * reports by Hijri month.
     *
     * @return array{year: int, month: int, day: int}
     */
    public static function hijriParts(DateTimeInterface $date): array
    {
        [$year, $month, $day] = array_map('intval', explode('-', self::format($date, 'islamic-umalqura', 'en', 'y-M-d')));

        return ['year' => $year, 'month' => $month, 'day' => $day];
    }

    private static function format(DateTimeInterface $date, string $calendar, ?string $locale, string $pattern): string
    {
        $formatter = new IntlDateFormatter(
            self::locale($locale).'@calendar='.$calendar.';numbers=latn',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $date->getTimezone(),
            $calendar === 'gregorian' ? IntlDateFormatter::GREGORIAN : IntlDateFormatter::TRADITIONAL,
            $pattern,
        );

        return $formatter->format($date);
    }

    private static function locale(?string $locale): string
    {
        return $locale ?? app()->getLocale();
    }
}
