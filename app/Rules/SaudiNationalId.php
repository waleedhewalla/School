<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Saudi national ID (starts with 1) or iqama number (starts with 2):
 * ten digits with a Luhn check digit.
 */
class SaudiNationalId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid((string) $value)) {
            $fail('validation.saudi_national_id')->translate();
        }
    }

    public static function isValid(string $value): bool
    {
        if (! preg_match('/^[12]\d{9}$/', $value)) {
            return false;
        }

        $sum = 0;
        foreach (str_split($value) as $index => $digit) {
            $digit = (int) $digit;
            if ($index % 2 === 0) {
                $digit *= 2;
                $digit = intdiv($digit, 10) + $digit % 10;
            }
            $sum += $digit;
        }

        return $sum % 10 === 0;
    }
}
