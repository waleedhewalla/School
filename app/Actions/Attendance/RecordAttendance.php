<?php

namespace App\Actions\Attendance;

use App\Events\StudentsMarkedAbsent;
use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a register for one section, date and period. Re-saving the same
 * register corrects it. Guardians are only notified about records that
 * changed to a code that asks for it, so corrections don't re-send alerts.
 */
class RecordAttendance
{
    /**
     * @param  list<array{student_id: int, code: string, note?: string|null}>  $rows
     * @return Collection<int, AttendanceRecord>
     */
    public function handle(Section $section, CarbonInterface $date, int $period, array $rows, User $by): Collection
    {
        $codes = AttendanceCode::query()->get()->keyBy('code');
        $roster = Student::query()->inSection($section->id)->pluck('id')->flip();

        foreach ($rows as $index => $row) {
            if (! $roster->has($row['student_id'])) {
                throw ValidationException::withMessages(["records.$index.student_id" => __('attendance.not_in_section')]);
            }
            if (! $codes->has($row['code'])) {
                throw ValidationException::withMessages(["records.$index.code" => __('attendance.unknown_code')]);
            }
        }

        [$saved, $toNotify] = DB::transaction(function () use ($section, $date, $period, $rows, $by, $codes) {
            $saved = collect();
            $toNotify = collect();

            foreach ($rows as $row) {
                $code = $codes[$row['code']];

                $record = AttendanceRecord::query()->firstOrNew([
                    'student_id' => $row['student_id'],
                    'date' => $date->toDateString(),
                    'period' => $period,
                ]);
                $codeChanged = $record->attendance_code_id !== $code->id;

                $record->fill([
                    'section_id' => $section->id,
                    'attendance_code_id' => $code->id,
                    'note' => $row['note'] ?? null,
                    'recorded_by' => $by->id,
                ])->save();

                $saved->push($record->setRelation('code', $code));

                if ($codeChanged && $code->notify_guardian) {
                    $toNotify->push($record);
                }
            }

            return [$saved, $toNotify];
        });

        if ($toNotify->isNotEmpty()) {
            StudentsMarkedAbsent::dispatch($section->school_id, $toNotify);
        }

        return $saved;
    }
}
