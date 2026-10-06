<?php

namespace App\Actions\Timetable;

use App\Models\Period;
use App\Models\Section;
use App\Models\TeachingAssignment;
use App\Models\TimetableEntry;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Validation\ValidationException;

/**
 * Puts a lesson (a teaching assignment of this section) in a day/period
 * slot, replacing whatever was there. Refuses when the teacher or the
 * room is already busy in another section at that time.
 */
class PlaceLesson
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(Section $section, int $day, Period $period, TeachingAssignment $assignment, ?string $room = null): TimetableEntry
    {
        if (! in_array($day, $this->currentSchool->get()->schoolDays(), true)) {
            throw ValidationException::withMessages(['day' => __('timetable.not_a_school_day')]);
        }
        if ($period->is_break) {
            throw ValidationException::withMessages(['period_id' => __('timetable.break_period')]);
        }
        if ((int) $assignment->section_id !== (int) $section->id) {
            throw ValidationException::withMessages(['teaching_assignment_id' => __('timetable.not_this_section')]);
        }

        $room = filled($room) ? trim($room) : null;
        $busy = TimetableEntry::query()
            ->with('section.gradeLevel')
            ->where('academic_year_id', $section->academic_year_id)
            ->where('day', $day)
            ->where('period_id', $period->id)
            ->where('section_id', '!=', $section->id);

        if ($clash = (clone $busy)->where('staff_member_id', $assignment->staff_member_id)->first()) {
            throw ValidationException::withMessages(['teaching_assignment_id' => __('timetable.teacher_busy', ['section' => $this->label($clash->section)])]);
        }
        if ($room !== null && ($clash = (clone $busy)->where('room', $room)->first())) {
            throw ValidationException::withMessages(['room' => __('timetable.room_busy', ['room' => $room, 'section' => $this->label($clash->section)])]);
        }

        return TimetableEntry::query()->updateOrCreate(
            ['section_id' => $section->id, 'day' => $day, 'period_id' => $period->id],
            [
                'academic_year_id' => $section->academic_year_id,
                'teaching_assignment_id' => $assignment->id,
                'staff_member_id' => $assignment->staff_member_id,
                'room' => $room,
            ],
        );
    }

    private function label(Section $section): string
    {
        return $section->gradeLevel->name.' / '.$section->name;
    }
}
