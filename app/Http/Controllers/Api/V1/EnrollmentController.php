<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Students\ChangeEnrollment;
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
    public function update(Request $request, Enrollment $enrollment, ChangeEnrollment $change): EnrollmentResource
    {
        $change->handle($enrollment, $request->validate(ChangeEnrollment::rules()));

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
