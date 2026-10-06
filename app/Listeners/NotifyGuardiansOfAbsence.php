<?php

namespace App\Listeners;

use App\Events\StudentsMarkedAbsent;
use App\Models\AttendanceRecord;
use App\Models\Guardian;
use App\Models\MessageLog;
use App\Models\School;
use App\Support\Dates\SchoolDate;
use App\Support\Messaging\SmsFailed;
use App\Support\Messaging\SmsGateway;
use App\Support\PhoneNumber;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Texts guardians when their child is newly marked absent or late. Primary
 * guardians are messaged; if none is marked primary, every guardian with a
 * phone is. Each attempt is logged in message_logs.
 */
class NotifyGuardiansOfAbsence implements ShouldQueue
{
    public function __construct(private SmsGateway $sms, private CurrentSchool $currentSchool) {}

    public function handle(StudentsMarkedAbsent $event): void
    {
        $school = School::query()->find($event->schoolId);

        if ($school === null || ! $school->isActive()) {
            return;
        }

        $this->currentSchool->run($school, function (School $school) use ($event) {
            $locale = $school->default_locale;

            AttendanceRecord::query()
                ->with(['code', 'student.guardians'])
                ->whereKey($event->recordIds)
                ->get()
                ->each(fn (AttendanceRecord $record) => $this->notify($school, $record, $locale));
        });
    }

    private function notify(School $school, AttendanceRecord $record, string $locale): void
    {
        $guardians = $record->student->guardians;
        $primary = $guardians->filter(fn (Guardian $g) => $g->pivot->is_primary);
        $recipients = ($primary->isNotEmpty() ? $primary : $guardians)
            ->filter(fn (Guardian $g) => PhoneNumber::normalize($g->phone) !== null);

        $body = __('notifications.attendance_sms_'.$record->student->gender->value, [
            'school' => $locale === 'en' && $school->name_en ? $school->name_en : $school->name_ar,
            'student' => $locale === 'en' && $record->student->name_en ? $record->student->name_en : $record->student->first_name_ar,
            'status' => $locale === 'en' && $record->code->name_en ? $record->code->name_en : $record->code->name_ar,
            'date' => SchoolDate::display($record->date, $school->date_display, $locale),
        ], $locale);

        foreach ($recipients as $guardian) {
            $to = PhoneNumber::normalize($guardian->phone);
            $status = 'sent';
            $error = null;

            try {
                $this->sms->send($to, $body);
            } catch (SmsFailed $e) {
                $status = 'failed';
                $error = mb_substr($e->getMessage(), 0, 250);
            }

            MessageLog::query()->create([
                'guardian_id' => $guardian->id,
                'student_id' => $record->student_id,
                'channel' => 'sms',
                'purpose' => 'attendance',
                'to' => $to,
                'body' => $body,
                'status' => $status,
                'error' => $error,
            ]);
        }
    }
}
