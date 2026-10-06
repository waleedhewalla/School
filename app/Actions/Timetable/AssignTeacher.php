<?php

namespace App\Actions\Timetable;

use App\Models\Section;
use App\Models\TeachingAssignment;
use App\Models\TimetableEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets who teaches a subject in a section. If the subject is already on
 * the timetable, its lessons move to the new teacher — refused when the
 * new teacher is busy elsewhere at any of those times.
 */
class AssignTeacher
{
    public function handle(Section $section, int $subjectId, int $staffMemberId, bool $isHomeroom = false): TeachingAssignment
    {
        return DB::transaction(function () use ($section, $subjectId, $staffMemberId, $isHomeroom) {
            $assignment = TeachingAssignment::query()->updateOrCreate(
                ['section_id' => $section->id, 'subject_id' => $subjectId],
                ['academic_year_id' => $section->academic_year_id, 'staff_member_id' => $staffMemberId, 'is_homeroom' => $isHomeroom],
            );

            $lessons = TimetableEntry::query()->where('teaching_assignment_id', $assignment->id)->get();

            foreach ($lessons as $lesson) {
                $clash = TimetableEntry::query()
                    ->with('section.gradeLevel')
                    ->where('academic_year_id', $lesson->academic_year_id)
                    ->where('staff_member_id', $staffMemberId)
                    ->where('day', $lesson->day)
                    ->where('period_id', $lesson->period_id)
                    ->whereKeyNot($lesson->id)
                    ->first();

                if ($clash) {
                    throw ValidationException::withMessages(['staff_member_id' => __('timetable.teacher_busy', [
                        'section' => $clash->section->gradeLevel->name.' / '.$clash->section->name,
                    ])]);
                }

                $lesson->update(['staff_member_id' => $staffMemberId]);
            }

            return $assignment;
        });
    }
}
