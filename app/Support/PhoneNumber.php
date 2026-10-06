<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalizes a Saudi mobile number to international form without "+"
     * (9665XXXXXXXX). Accepts 05XXXXXXXX, 5XXXXXXXX, +9665…, 009665…
     * Other countries' numbers are returned as digits only. Null when the
     * value is not a usable number.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', strtr($value, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));

        $digits = match (true) {
            str_starts_with($digits, '00') => substr($digits, 2),
            str_starts_with($digits, '05') && strlen($digits) === 10 => '966'.substr($digits, 1),
            str_starts_with($digits, '5') && strlen($digits) === 9 => '966'.$digits,
            default => $digits,
        };

        if (str_starts_with($digits, '966')) {
            return preg_match('/^9665\d{8}$/', $digits) ? $digits : null;
        }

        return strlen($digits) >= 9 && strlen($digits) <= 15 ? $digits : null;
    }
}
