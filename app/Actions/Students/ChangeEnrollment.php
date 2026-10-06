<?php

namespace App\Actions\Students;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Section;
use App\Rules\ExistsInCurrentSchool;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Moves an active enrollment to another section of the same grade, or ends it (withdrawn / transferred). */
class ChangeEnrollment
{
    public function __construct(private EnrollStudent $enroll) {}

    /** @param  array{section_id?: int|null, status?: string, left_on?: string}  $data */
    public function handle(Enrollment $enrollment, array $data): Enrollment
    {
        if ($enrollment->status !== EnrollmentStatus::Active) {
            throw ValidationException::withMessages(['status' => __('students.enrollment_closed')]);
        }

        if (($data['section_id'] ?? null) !== null) {
            $section = Section::query()->findOrFail($data['section_id']);

            if ((int) $section->academic_year_id !== (int) $enrollment->academic_year_id
                || (int) $section->grade_level_id !== (int) $enrollment->grade_level_id) {
                throw ValidationException::withMessages(['section_id' => __('students.section_mismatch')]);
            }

            if ((int) $section->id !== (int) $enrollment->section_id) {
                $this->enroll->ensureCapacity($section);
            }
        }

        $enrollment->update($data);

        if (isset($data['status'])) {
            $enrollment->student->update(['status' => $data['status']]);
        }

        return $enrollment;
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'section_id' => ['sometimes', 'nullable', 'integer', ExistsInCurrentSchool::in('sections')],
            'status' => ['sometimes', Rule::in([EnrollmentStatus::Transferred->value, EnrollmentStatus::Withdrawn->value])],
            'left_on' => ['required_with:status', 'date'],
        ];
    }
}
