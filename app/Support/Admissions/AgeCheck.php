<?php

namespace App\Support\Admissions;

use App\Models\AdmissionWindow;
use Carbon\CarbonInterface;

/**
 * Checks a birth date against the grade's window for the year. Children up
 * to `exception_days` younger than the range (e.g. the 90-day grade 1
 * exception after a full KG year) are flagged for a staff decision, not
 * refused.
 */
class AgeCheck
{
    public const OK = 'ok';

    public const EXCEPTION = 'exception';

    public const OUTSIDE = 'outside';

    public static function evaluate(AdmissionWindow $window, CarbonInterface $birth): string
    {
        if ($window->born_from && $birth->lt($window->born_from)) {
            return self::OUTSIDE; // too old for this grade
        }
        if ($window->born_to && $birth->gt($window->born_to)) {
            return $birth->lte($window->born_to->copy()->addDays($window->exception_days)) ? self::EXCEPTION : self::OUTSIDE;
        }

        return self::OK;
    }
}
