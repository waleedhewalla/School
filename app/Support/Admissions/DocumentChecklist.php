<?php

namespace App\Support\Admissions;

use App\Models\Application;

/**
 * Documents a family uploads, depending on grade and background. Based on
 * what Saudi private schools commonly ask for (see docs/market-research.md).
 */
class DocumentChecklist
{
    public const TYPES = ['birth_certificate', 'identity', 'photo', 'vaccination', 'report_card', 'financial_clearance'];

    /** @return list<string> required document types for this application */
    public static function required(Application $application): array
    {
        $grade = $application->window->gradeLevel;
        $stage = $grade->stage->code;
        $types = ['birth_certificate', 'identity', 'photo'];

        // Young children: vaccination record (KG and grade 1).
        if ($stage === 'kg' || ($stage === 'primary' && (int) $grade->sequence === 1)) {
            $types[] = 'vaccination';
        }
        // Coming from another school: last report card.
        if (filled($application->current_school)) {
            $types[] = 'report_card';
        }
        // Leaving a private school: financial clearance (مخالصة مالية).
        if ($application->from_private_school) {
            $types[] = 'financial_clearance';
        }

        return $types;
    }
}
