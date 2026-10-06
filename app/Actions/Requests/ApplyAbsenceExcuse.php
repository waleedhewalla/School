<?php

namespace App\Actions\Requests;

use App\Enums\AttendanceKind;
use App\Models\AbsenceExcuse;
use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use Carbon\CarbonInterface;

/**
 * Once an excuse is approved, the child's absences in its date range
 * become "absent with excuse" — those already recorded now, and any
 * recorded later (RecordAttendance asks excusedCode()).
 */
class ApplyAbsenceExcuse
{
    public function handle(AbsenceExcuse $excuse): int
    {
        $excused = self::excusedCodeId();
        if ($excused === null) {
            return 0;
        }

        return AttendanceRecord::query()
            ->where('student_id', $excuse->student_id)
            ->whereBetween('date', [$excuse->from_date->toDateString(), $excuse->to_date->toDateString()])
            ->whereIn('attendance_code_id', AttendanceCode::query()->where('kind', AttendanceKind::Absent)->pluck('id'))
            ->update(['attendance_code_id' => $excused, 'note' => __('attendance.excuse_approved')]);
    }

    /** @return array<int, true> students (keys) with an approved excuse covering the date */
    public static function excusedStudents(iterable $studentIds, CarbonInterface $date): array
    {
        return AbsenceExcuse::query()->where('status', AbsenceExcuse::APPROVED)
            ->whereIn('student_id', collect($studentIds)->all())
            ->where('from_date', '<=', $date->toDateString())->where('to_date', '>=', $date->toDateString())
            ->pluck('student_id')->flip()->map(fn () => true)->all();
    }

    public static function excusedCodeId(): ?int
    {
        return AttendanceCode::query()->where('kind', AttendanceKind::Excused)->orderBy('sequence')->value('id');
    }
}
