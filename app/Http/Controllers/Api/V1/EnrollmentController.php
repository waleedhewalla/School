<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Students\EnrollStudent;
use App\Actions\Students\PromoteStudents;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\EnrollmentResource;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use App\Rules\ExistsInCurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    public function store(Request $request, EnrollStudent $enroll): EnrollmentResource
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', ExistsInCurrentSchool::in('students')],
            'academic_year_id' => ['required', 'integer', ExistsInCurrentSchool::in('academic_years')],
            'grade_level_id' => ['required', 'integer', ExistsInCurrentSchool::inTable('grade_levels')],
            'section_id' => ['nullable', 'integer', ExistsInCurrentSchool::in('sections')],
        ]);

        $enrollment = $enroll->handle(
            Student::query()->findOrFail($data['student_id']),
            AcademicYear::query()->findOrFail($data['academic_year_id']),
            GradeLevel::query()->findOrFail($data['grade_level_id']),
            isset($data['section_id']) ? Section::query()->findOrFail($data['section_id']) : null,
        );

        return new EnrollmentResource($enrollment);
    }

    /** Move to another section of the same grade and year, or end the enrollment. */
    public function update(Request $request, Enrollment $enrollment, EnrollStudent $enroll): EnrollmentResource
    {
        $data = $request->validate([
            'section_id' => ['sometimes', 'nullable', 'integer', ExistsInCurrentSchool::in('sections')],
            'status' => ['sometimes', Rule::in([EnrollmentStatus::Transferred->value, EnrollmentStatus::Withdrawn->value])],
            'left_on' => ['required_with:status', 'date'],
        ]);

        if ($enrollment->status !== EnrollmentStatus::Active) {
            throw ValidationException::withMessages(['status' => __('students.enrollment_closed')]);
        }

        if (array_key_exists('section_id', $data) && $data['section_id'] !== null) {
            $section = Section::query()->findOrFail($data['section_id']);
            if ((int) $section->academic_year_id !== (int) $enrollment->academic_year_id || (int) $section->grade_level_id !== (int) $enrollment->grade_level_id) {
                throw ValidationException::withMessages(['section_id' => __('students.section_mismatch')]);
            }
            if ((int) $section->id !== (int) $enrollment->section_id) {
                $enroll->ensureCapacity($section);
            }
        }

        $enrollment->update($data);

        if (isset($data['status'])) {
            $enrollment->student->update(['status' => $data['status']]);
        }

        return new EnrollmentResource($enrollment->load('section', 'gradeLevel'));
    }

    public function promote(Request $request, PromoteStudents $promote): AnonymousResourceCollection
    {
        $data = $request->validate([
            'from_academic_year_id' => ['required', 'integer', ExistsInCurrentSchool::in('academic_years')],
            'to_academic_year_id' => ['required', 'integer', ExistsInCurrentSchool::in('academic_years')],
            'decisions' => ['required', 'array', 'min:1', 'max:1000'],
            'decisions.*.student_id' => ['required', 'integer', 'distinct'],
            'decisions.*.outcome' => ['required', Rule::in([
                EnrollmentStatus::Promoted->value, EnrollmentStatus::Repeated->value, EnrollmentStatus::Graduated->value,
            ])],
            'decisions.*.section_id' => ['nullable', 'integer', ExistsInCurrentSchool::in('sections')],
        ]);

        $created = $promote->handle(
            AcademicYear::query()->findOrFail($data['from_academic_year_id']),
            AcademicYear::query()->findOrFail($data['to_academic_year_id']),
            $data['decisions'],
        );

        return EnrollmentResource::collection($created);
    }
}
