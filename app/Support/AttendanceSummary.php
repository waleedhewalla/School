<?php

namespace App\Support;

use App\Enums\AttendanceKind;
use App\Models\AttendanceRecord;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Daily-register totals per student over a date range (e.g. a term). */
class AttendanceSummary
{
    /**
     * @param  iterable<int>  $studentIds
     * @return Collection<int, array{present: int, absent: int, late: int, excused: int, recorded: int}> keyed by student id
     */
    public static function forStudents(iterable $studentIds, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $empty = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'recorded' => 0];

        $rows = AttendanceRecord::query()
            ->join('attendance_codes', 'attendance_codes.id', '=', 'attendance_records.attendance_code_id')
            ->whereIn('attendance_records.student_id', collect($studentIds)->all())
            ->where('attendance_records.period', AttendanceRecord::DAILY)
            ->whereBetween('attendance_records.date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('attendance_records.student_id, attendance_codes.kind, count(*) as total')
            ->groupBy('attendance_records.student_id', 'attendance_codes.kind')
            ->get();

        return collect($studentIds)->mapWithKeys(function (int $id) use ($rows, $empty) {
            $summary = $empty;
            foreach ($rows->where('student_id', $id) as $row) {
                $summary[$row->kind] = (int) $row->total;
                $summary['recorded'] += (int) $row->total;
            }

            return [$id => $summary];
        });
    }

    /** Share of recorded school days the student was in school (present or late), 0–100. */
    public static function rate(array $summary): ?float
    {
        return $summary['recorded'] > 0
            ? round(($summary[AttendanceKind::Present->value] + $summary[AttendanceKind::Late->value]) / $summary['recorded'] * 100, 1)
            : null;
    }
}
