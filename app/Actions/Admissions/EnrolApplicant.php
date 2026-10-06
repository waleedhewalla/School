<?php

namespace App\Actions\Admissions;

use App\Actions\Students\AdmitStudent;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Support\GuardianMatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns an accepted application into a student enrolled in the window's
 * year and grade. A guardian already in the school (by ID or mobile) is
 * reused, so the child joins their siblings' family.
 */
class EnrolApplicant
{
    public function __construct(private AdmitStudent $admit, private ChangeApplicationStatus $status) {}

    public function handle(Application $application, ?Section $section, ?User $by = null): Student
    {
        if ($application->status !== ApplicationStatus::Accepted) {
            throw ValidationException::withMessages(['status' => __('admissions.not_accepted')]);
        }
        if (filled($application->national_id) && Student::query()->where('national_id', $application->national_id)->exists()) {
            throw ValidationException::withMessages(['national_id' => __('admissions.already_a_student')]);
        }

        return DB::transaction(function () use ($application, $section, $by) {
            $window = $application->window;
            $existing = GuardianMatcher::findStrict($application->guardian_national_id, $application->guardian_phone);

            $guardian = $existing
                ? ['guardian_id' => $existing->id]
                : [
                    'name_ar' => $application->guardian_name,
                    'national_id' => $application->guardian_national_id,
                    'phone' => $application->guardian_phone,
                    'email' => $application->guardian_email,
                ];

            $student = $this->admit->handle([
                'first_name_ar' => $application->first_name_ar,
                'father_name_ar' => $application->father_name_ar,
                'grandfather_name_ar' => $application->grandfather_name_ar,
                'family_name_ar' => $application->family_name_ar,
                'name_en' => $application->name_en,
                'gender' => $application->gender->value,
                'date_of_birth' => $application->date_of_birth->toDateString(),
                'national_id' => $application->national_id,
                'nationality' => $application->nationality,
                'guardians' => [$guardian + ['relationship' => $application->guardian_relationship, 'is_primary' => true]],
                'enrollment' => [
                    'academic_year_id' => $window->academic_year_id,
                    'grade_level_id' => $window->grade_level_id,
                    'section_id' => $section?->id,
                ],
            ]);

            $application->update(['student_id' => $student->id]);
            $this->status->handle($application, ApplicationStatus::Enrolled, $by);

            return $student;
        });
    }
}
