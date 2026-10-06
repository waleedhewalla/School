<?php

namespace App\Support;

use App\Models\Period;
use App\Models\TimetableEntry;
use Illuminate\Database\Eloquent\Builder;

/** Shapes timetable entries into rows (periods) × columns (days) for screens and the API. */
class TimetableGrid
{
    /**
     * @param  Builder<TimetableEntry>  $entries
     * @return array{days: list<array{day: int, name: string}>, periods: list<array<string, mixed>>, cells: array<string, array<string, mixed>>}
     */
    public static function build(Builder $entries, array $schoolDays, bool $withSection = false): array
    {
        $cells = $entries->with(['teachingAssignment.subject', 'staffMember', 'section.gradeLevel', 'period'])->get()
            ->mapWithKeys(fn (TimetableEntry $e) => [$e->day.'-'.$e->period_id => [
                'id' => $e->id,
                'teaching_assignment_id' => $e->teaching_assignment_id,
                'subject' => $e->teachingAssignment->subject->name,
                'teacher' => $e->staffMember->name,
                'room' => $e->room,
                'section_id' => $e->section_id,
                'section' => $withSection ? $e->section->gradeLevel->name.' / '.$e->section->name : null,
                'period_sequence' => $e->period->sequence,
            ]]);

        return [
            'days' => array_map(fn (int $day) => ['day' => $day, 'name' => self::dayName($day)], $schoolDays),
            'periods' => Period::query()->orderBy('sequence')->get()->map(fn (Period $p) => [
                'id' => $p->id,
                'sequence' => $p->sequence,
                'name' => $p->name,
                'starts_at' => $p->startsAtShort(),
                'ends_at' => $p->endsAtShort(),
                'is_break' => $p->is_break,
            ])->all(),
            'cells' => $cells->all(),
        ];
    }

    public static function dayName(int $day): string
    {
        return __('days.'.$day);
    }
}
