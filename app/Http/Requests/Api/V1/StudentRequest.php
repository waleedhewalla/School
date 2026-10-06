<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Models\Student;
use App\Rules\ExistsInCurrentSchool;
use App\Rules\SaudiNationalId;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Student|null $student */
        $student = $this->route('student');
        $creating = $student === null;
        $schoolId = app(CurrentSchool::class)->id();

        return [
            'student_number' => ['nullable', 'string', 'alpha_dash:ascii', 'max:30',
                Rule::unique('students')->where('school_id', $schoolId)->ignore($student?->id)],
            'national_id' => ['nullable', 'string', new SaudiNationalId,
                Rule::unique('students')->where('school_id', $schoolId)->ignore($student?->id)],
            'first_name_ar' => [Rule::requiredIf($creating), 'string', 'max:60'],
            'father_name_ar' => ['nullable', 'string', 'max:60'],
            'grandfather_name_ar' => ['nullable', 'string', 'max:60'],
            'family_name_ar' => [Rule::requiredIf($creating), 'string', 'max:60'],
            'name_en' => ['nullable', 'string', 'max:200'],
            'gender' => [Rule::requiredIf($creating), Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nationality' => ['sometimes', 'string', 'size:2'],

            // Only when admitting: guardians and the first enrollment.
            'guardians' => [Rule::prohibitedIf(! $creating), 'array', 'max:4'],
            'guardians.*.guardian_id' => ['nullable', 'integer', ExistsInCurrentSchool::in('guardians')],
            'guardians.*.name_ar' => ['required_without:guardians.*.guardian_id', 'string', 'max:150'],
            'guardians.*.name_en' => ['nullable', 'string', 'max:150'],
            'guardians.*.national_id' => ['nullable', 'string', new SaudiNationalId],
            'guardians.*.phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
            'guardians.*.email' => ['nullable', 'email'],
            'guardians.*.relationship' => ['required', Rule::enum(GuardianRelationship::class)],
            'guardians.*.is_primary' => ['sometimes', 'boolean'],

            'enrollment' => [Rule::prohibitedIf(! $creating), 'array'],
            'enrollment.academic_year_id' => ['required_with:enrollment', 'integer', ExistsInCurrentSchool::in('academic_years')],
            'enrollment.grade_level_id' => ['required_with:enrollment', 'integer', ExistsInCurrentSchool::inTable('grade_levels')],
            'enrollment.section_id' => ['nullable', 'integer', ExistsInCurrentSchool::in('sections')],
        ];
    }
}
