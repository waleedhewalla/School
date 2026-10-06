<?php

namespace App\Listeners;

use App\Events\StudentsMarkedAbsent;
use App\Mail\AttendanceAlert;
use App\Models\AttendanceRecord;
use App\Models\Guardian;
use App\Models\MessageLog;
use App\Models\School;
use App\Support\Dates\SchoolDate;
use App\Support\Messaging\SmsFailed;
use App\Support\Messaging\SmsGateway;
use App\Support\Messaging\WhatsAppGateway;
use App\Support\PhoneNumber;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells guardians when their child is newly marked absent or late, on the
 * channels the school has switched on (SMS, WhatsApp, email). Primary
 * guardians are contacted; if none is marked primary, all guardians are.
 * Alerts raised during the school's quiet hours wait until they end.
 * Every attempt is logged in message_logs.
 */
class NotifyGuardiansOfAbsence implements ShouldQueue
{
    public function __construct(
        private SmsGateway $sms,
        private WhatsAppGateway $whatsapp,
        private CurrentSchool $currentSchool,
    ) {}

    /** Seconds to hold the alert when it falls in the school's quiet hours. */
    public function withDelay(StudentsMarkedAbsent $event): int
    {
        $school = School::query()->find($event->schoolId);

        return $school ? self::secondsUntilQuietHoursEnd($school, CarbonImmutable::now()) : 0;
    }

    public static function secondsUntilQuietHoursEnd(School $school, CarbonImmutable $now): int
    {
        $quiet = $school->notificationSettings()['quiet_hours'];
        if (! $quiet['enabled']) {
            return 0;
        }

        $local = $now->setTimezone($school->timezone);
        $start = $local->setTimeFromTimeString($quiet['start']);
        $end = $local->setTimeFromTimeString($quiet['end']);

        if ($start <= $end) {
            $inQuiet = $local >= $start && $local < $end;
        } else {
            // Spans midnight, e.g. 21:00 → 06:30.
            $inQuiet = $local >= $start || $local < $end;
            if ($local >= $start) {
                $end = $end->addDay();
            }
        }

        return $inQuiet ? (int) $local->diffInSeconds($end) : 0;
    }

    public function handle(StudentsMarkedAbsent $event): void
    {
        $school = School::query()->find($event->schoolId);

        if ($school === null || ! $school->isActive()) {
            return;
        }

        $this->currentSchool->run($school, function (School $school) use ($event) {
            AttendanceRecord::query()
                ->with(['code', 'student.guardians'])
                ->whereKey($event->recordIds)
                ->get()
                ->each(fn (AttendanceRecord $record) => $this->notify($school, $record));
        });
    }

    private function notify(School $school, AttendanceRecord $record): void
    {
        $locale = $school->default_locale;
        $channels = $school->notificationSettings()['channels'];
        $en = $locale === 'en';

        $values = [
            'school' => $en && $school->name_en ? $school->name_en : $school->name_ar,
            'student' => $en && $record->student->name_en ? $record->student->name_en : $record->student->first_name_ar,
            'status' => $en && $record->code->name_en ? $record->code->name_en : $record->code->name_ar,
            'date' => SchoolDate::display($record->date, $school->date_display, $locale),
        ];
        $body = __('notifications.attendance_sms_'.$record->student->gender->value, $values, $locale);

        $guardians = $record->student->guardians;
        $primary = $guardians->filter(fn (Guardian $g) => $g->pivot->is_primary);

        foreach ($primary->isNotEmpty() ? $primary : $guardians as $guardian) {
            $phone = PhoneNumber::normalize($guardian->phone);

            if ($channels['sms'] && $phone) {
                $this->attempt($record, $guardian, 'sms', $phone, $body, fn () => $this->sms->send($phone, $body));
            }
            if ($channels['whatsapp'] && $phone) {
                $this->attempt($record, $guardian, 'whatsapp', $phone, $body, fn () => $this->whatsapp->sendTemplate(
                    $phone, config('services.whatsapp.attendance_template'), $locale, array_values($values),
                ));
            }
            if ($channels['email'] && filled($guardian->email)) {
                $this->attempt($record, $guardian, 'email', $guardian->email, $body, fn () => Mail::to($guardian->email)
                    ->send(new AttendanceAlert($values['school'], $body, $locale)));
            }
        }
    }

    private function attempt(AttendanceRecord $record, Guardian $guardian, string $channel, string $to, string $body, callable $send): void
    {
        $status = 'sent';
        $error = null;

        try {
            $send();
        } catch (SmsFailed $e) {
            [$status, $error] = ['failed', $e->getMessage()];
        } catch (Throwable $e) {
            report($e);
            [$status, $error] = ['failed', class_basename($e).': '.$e->getMessage()];
        }

        MessageLog::query()->create([
            'guardian_id' => $guardian->id,
            'student_id' => $record->student_id,
            'channel' => $channel,
            'purpose' => 'attendance',
            'to' => $to,
            'body' => $body,
            'status' => $status,
            'error' => $error === null ? null : mb_substr($error, 0, 250),
        ]);
    }
}
