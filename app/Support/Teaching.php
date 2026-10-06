<?php

namespace App\Support;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/** Who teaches what: the sections and subjects a user is assigned to this year. */
class Teaching
{
    /** @return Collection<int, TeachingAssignment> the user's assignments in the current year */
    public static function assignmentsOf(User $user): Collection
    {
        return TeachingAssignment::query()
            ->with('section.gradeLevel', 'subject')
            ->whereHas('staffMember', fn ($q) => $q->where('user_id', $user->getKey()))
            ->whereHas('section.academicYear', fn ($q) => $q->where('is_current', true))
            ->get();
    }

    /** @return list<int> */
    public static function sectionIdsOf(User $user): array
    {
        return self::assignmentsOf($user)->pluck('section_id')->unique()->values()->all();
    }

    public static function teachesSubject(User $user, int $sectionId, int $subjectId): bool
    {
        return TeachingAssignment::query()->where('section_id', $sectionId)->where('subject_id', $subjectId)
            ->whereHas('staffMember', fn ($q) => $q->where('user_id', $user->getKey()))
            ->whereHas('section.academicYear', fn ($q) => $q->where('is_current', true))->exists();
    }

    /** Whether the user teaches the student's current section. */
    public static function teachesStudent(User $user, Student $student): bool
    {
        $sectionId = Enrollment::query()->where('student_id', $student->id)->where('status', EnrollmentStatus::Active)
            ->whereHas('academicYear', fn ($q) => $q->where('is_current', true))->value('section_id');

        return $sectionId !== null && in_array($sectionId, self::sectionIdsOf($user), true);
    }
}
