<?php

namespace App\Actions\Students;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class EnrollStudent
{
    public function handle(
        Student $student,
        AcademicYear $year,
        GradeLevel $gradeLevel,
        ?Section $section = null,
        ?CarbonInterface $on = null,
    ): Enrollment {
        if ($section !== null && ((int) $section->academic_year_id !== (int) $year->id || (int) $section->grade_level_id !== (int) $gradeLevel->id)) {
            throw ValidationException::withMessages(['section_id' => __('students.section_mismatch')]);
        }

        if ($student->enrollments()->where('academic_year_id', $year->id)->exists()) {
            throw ValidationException::withMessages(['academic_year_id' => __('students.already_enrolled')]);
        }

        $this->ensureCapacity($section);

        return $student->enrollments()->create([
            'academic_year_id' => $year->id,
            'grade_level_id' => $gradeLevel->id,
            'section_id' => $section?->id,
            'status' => EnrollmentStatus::Active,
            'enrolled_on' => ($on ?? now())->toDateString(),
        ]);
    }

    public function ensureCapacity(?Section $section): void
    {
        if ($section?->capacity === null) {
            return;
        }

        $taken = $section->enrollments()->where('status', EnrollmentStatus::Active)->count();

        if ($taken >= $section->capacity) {
            throw ValidationException::withMessages(['section_id' => __('students.section_full')]);
        }
    }
}
