<?php

namespace App\Jobs;

use App\Mail\SchoolNotice;
use App\Models\BehaviourIncident;
use App\Models\Guardian;
use App\Models\MessageLog;
use App\Models\School;
use App\Support\Dates\SchoolDate;
use App\Support\Messaging\SmsFailed;
use App\Support\Messaging\SmsGateway;
use App\Support\PhoneNumber;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Tells the primary guardian about a behaviour record whose category asks for it (SMS / email). */
class NotifyGuardianOfBehaviour implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $schoolId, public int $incidentId) {}

    public function handle(SmsGateway $sms, CurrentSchool $currentSchool): void
    {
        $school = School::query()->find($this->schoolId);
        if ($school === null || ! $school->isActive()) {
            return;
        }

        $currentSchool->run($school, function (School $school) use ($sms) {
            $incident = BehaviourIncident::query()->with('category', 'student.guardians')->find($this->incidentId);
            if ($incident === null || $incident->guardian_notified_at !== null) {
                return;
            }

            $locale = $school->default_locale;
            $en = $locale === 'en';
            $student = $incident->student;
            $body = __('behaviour.sms_'.$incident->category->kind.'_'.$student->gender->value, [
                'school' => $en && $school->name_en ? $school->name_en : $school->name_ar,
                'student' => $en && $student->name_en ? $student->name_en : $student->first_name_ar,
                'category' => $en && $incident->category->name_en ? $incident->category->name_en : $incident->category->name_ar,
                'date' => SchoolDate::display($incident->occurred_on, $school->date_display, $locale),
            ], $locale);

            $guardians = $student->guardians;
            $primary = $guardians->filter(fn (Guardian $g) => $g->pivot->is_primary);
            $channels = $school->notificationSettings()['channels'];

            foreach ($primary->isNotEmpty() ? $primary : $guardians as $guardian) {
                if ($channels['sms'] && ($phone = PhoneNumber::normalize($guardian->phone))) {
                    $this->attempt($incident, $guardian, 'sms', $phone, $body, fn () => $sms->send($phone, $body));
                }
                if ($channels['email'] && filled($guardian->email)) {
                    $name = $en && $school->name_en ? $school->name_en : $school->name_ar;
                    $this->attempt($incident, $guardian, 'email', $guardian->email, $body, fn () => Mail::to($guardian->email)->send(new SchoolNotice($name, __('behaviour.subject', [], $locale), $body, $locale)));
                }
            }

            $incident->forceFill(['guardian_notified_at' => now()])->save();
        });
    }

    private function attempt(BehaviourIncident $incident, Guardian $guardian, string $channel, string $to, string $body, callable $send): void
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
            'guardian_id' => $guardian->id, 'student_id' => $incident->student_id, 'channel' => $channel, 'purpose' => 'behaviour',
            'to' => $to, 'body' => $body, 'status' => $status, 'error' => $error === null ? null : mb_substr($error, 0, 250),
        ]);
    }
}
