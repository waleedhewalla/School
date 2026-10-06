<?php

namespace App\Jobs;

use App\Mail\SchoolNotice;
use App\Models\Guardian;
use App\Models\MessageLog;
use App\Models\School;
use App\Models\Student;
use App\Support\Messaging\SmsFailed;
use App\Support\Messaging\SmsGateway;
use App\Support\PhoneNumber;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends a short translated message about one student to their primary
 * guardians (all guardians when none is primary) by SMS and email, as the
 * school's channels allow. The message key gets "_male"/"_female" added so
 * Arabic wording agrees with the child; :school and :student are filled in.
 */
class NotifyStudentGuardians implements ShouldQueue
{
    use Queueable;

    /** @param  array<string, string>  $values  extra placeholders */
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public string $purpose,
        public string $messageKey,
        public string $subjectKey,
        public array $values = [],
    ) {}

    public function handle(SmsGateway $sms, CurrentSchool $currentSchool): void
    {
        $school = School::query()->find($this->schoolId);
        if ($school === null || ! $school->isActive()) {
            return;
        }

        $currentSchool->run($school, function (School $school) use ($sms) {
            $student = Student::query()->with('guardians')->find($this->studentId);
            if ($student === null) {
                return;
            }

            $locale = $school->default_locale;
            $en = $locale === 'en';
            $schoolName = $en && $school->name_en ? $school->name_en : $school->name_ar;
            $body = __($this->messageKey.'_'.$student->gender->value, $this->values + [
                'school' => $schoolName,
                'student' => $en && $student->name_en ? $student->name_en : $student->first_name_ar,
            ], $locale);
            $channels = $school->notificationSettings()['channels'];

            $primary = $student->guardians->filter(fn (Guardian $g) => $g->pivot->is_primary);
            foreach ($primary->isNotEmpty() ? $primary : $student->guardians as $guardian) {
                if ($channels['sms'] && ($phone = PhoneNumber::normalize($guardian->phone))) {
                    $this->attempt($guardian, 'sms', $phone, $body, fn () => $sms->send($phone, $body));
                }
                if ($channels['email'] && filled($guardian->email)) {
                    $this->attempt($guardian, 'email', $guardian->email, $body, fn () => Mail::to($guardian->email)
                        ->send(new SchoolNotice($schoolName, __($this->subjectKey, [], $locale), $body, $locale)));
                }
            }
        });
    }

    private function attempt(Guardian $guardian, string $channel, string $to, string $body, callable $send): void
    {
        [$status, $error] = ['sent', null];
        try {
            $send();
        } catch (SmsFailed $e) {
            [$status, $error] = ['failed', $e->getMessage()];
        } catch (Throwable $e) {
            report($e);
            [$status, $error] = ['failed', class_basename($e).': '.$e->getMessage()];
        }

        MessageLog::query()->create([
            'guardian_id' => $guardian->id, 'student_id' => $this->studentId, 'channel' => $channel, 'purpose' => $this->purpose,
            'to' => $to, 'body' => $body, 'status' => $status, 'error' => $error === null ? null : mb_substr($error, 0, 250),
        ]);
    }
}
