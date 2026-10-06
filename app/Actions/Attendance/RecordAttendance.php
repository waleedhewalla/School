<?php

namespace App\Actions\Attendance;

use App\Actions\Requests\ApplyAbsenceExcuse;
use App\Enums\AttendanceKind;
use App\Enums\Permission;
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
 * register corrects it. Guardians are notified at most once per record,
 * when it first changes to a code that asks for it.
 */
class RecordAttendance
{
    /**
     * @param  list<array{student_id: int, code: string, note?: string|null}>  $rows
     * @return Collection<int, AttendanceRecord>
     */
    /** Teachers may correct the last week; older registers need attendance.manage. */
    public const TEACHER_BACKDATE_DAYS = 7;

    public function handle(Section $section, CarbonInterface $date, int $period, array $rows, User $by): Collection
    {
        if (! $by->can(Permission::AttendanceManage) && $date->lt(now()->subDays(self::TEACHER_BACKDATE_DAYS)->startOfDay())) {
            throw ValidationException::withMessages(['date' => __('attendance.too_old', ['days' => self::TEACHER_BACKDATE_DAYS])]);
        }

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

        // Absences already covered by an approved excuse are saved as excused.
        $excused = ApplyAbsenceExcuse::excusedStudents(array_column($rows, 'student_id'), $date);
        $excusedCode = $excused ? $codes->first(fn (AttendanceCode $c) => $c->id === ApplyAbsenceExcuse::excusedCodeId()) : null;

        [$saved, $toNotify] = DB::transaction(function () use ($section, $date, $period, $rows, $by, $codes, $excused, $excusedCode) {
            $saved = collect();
            $toNotify = collect();

            foreach ($rows as $row) {
                $code = $codes[$row['code']];
                if ($excusedCode && isset($excused[$row['student_id']]) && $code->kind === AttendanceKind::Absent) {
                    $code = $excusedCode;
                }

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

                // One alert per student, day and period, however often it's corrected.
                if ($codeChanged && $code->notify_guardian && $record->guardian_notified_at === null) {
                    $record->forceFill(['guardian_notified_at' => now()])->save();
                    $toNotify->push($record);
                }
            }

            return [$saved, $toNotify];
        });

        if ($toNotify->isNotEmpty()) {
            StudentsMarkedAbsent::dispatch($section->school_id, $toNotify->pluck('id')->all());
        }

        return $saved;
    }
}
