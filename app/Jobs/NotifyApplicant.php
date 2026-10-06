<?php

namespace App\Jobs;

use App\Listeners\NotifyGuardiansOfAbsence;
use App\Mail\AdmissionUpdate;
use App\Models\Application;
use App\Models\MessageLog;
use App\Models\School;
use App\Support\Dates\SchoolDate;
use App\Support\Messaging\SmsFailed;
use App\Support\Messaging\SmsGateway;
use App\Support\Messaging\WhatsAppGateway;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells the family about their application: on submission, and when staff
 * schedule an assessment, offer, waitlist, reject or enrol. Uses the
 * school's SMS and WhatsApp settings; email goes to the address on the
 * form, if any. Staff-triggered messages wait out the quiet hours.
 */
class NotifyApplicant implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $schoolId, public int $applicationId, public string $kind)
    {
        $school = School::query()->find($schoolId);
        if ($school !== null && $kind !== 'submitted') {
            $this->delay(NotifyGuardiansOfAbsence::secondsUntilQuietHoursEnd($school, CarbonImmutable::now()));
        }
    }

    public function handle(SmsGateway $sms, WhatsAppGateway $whatsapp, CurrentSchool $currentSchool): void
    {
        $school = School::query()->find($this->schoolId);
        if ($school === null || ! $school->isActive()) {
            return;
        }

        $currentSchool->run($school, function (School $school) use ($sms, $whatsapp) {
            $application = Application::query()->with('window.gradeLevel')->find($this->applicationId);
            if ($application === null) {
                return;
            }

            $locale = $school->default_locale;
            $en = $locale === 'en';
            $values = [
                'school' => $en && $school->name_en ? $school->name_en : $school->name_ar,
                'student' => $en && $application->name_en ? $application->name_en : $application->first_name_ar,
                // Isolated so "1449-0006" keeps its order inside Arabic text.
                'reference' => $en ? $application->reference : "\u{2066}{$application->reference}\u{2069}",
                'grade' => $en && $application->window->gradeLevel->name_en ? $application->window->gradeLevel->name_en : $application->window->gradeLevel->name_ar,
                'link' => $application->statusUrl(),
                'date' => $application->assessment_at ? SchoolDate::display($application->assessment_at->setTimezone($school->timezone), $school->date_display, $locale) : '',
                'time' => $application->assessment_at?->setTimezone($school->timezone)->format('H:i') ?? '',
            ];
            $body = __('admissions.message_'.$this->kind, $values, $locale);
            $channels = $school->notificationSettings()['channels'];
            $phone = $application->guardian_phone;

            if ($channels['sms'] && $phone) {
                $this->attempt('sms', $phone, $body, fn () => $sms->send($phone, $body));
            }
            if ($channels['whatsapp'] && $phone) {
                $this->attempt('whatsapp', $phone, $body, fn () => $whatsapp->sendTemplate(
                    $phone, config('services.whatsapp.admission_template'), $locale,
                    [$values['school'], $values['reference'], __('admissions.status.'.$application->status->value, [], $locale), $values['link']],
                ));
            }
            if (filled($application->guardian_email)) {
                $this->attempt('email', $application->guardian_email, $body, fn () => Mail::to($application->guardian_email)
                    ->send(new AdmissionUpdate($values['school'], $application->reference, $body, $locale)));
            }
        });
    }

    private function attempt(string $channel, string $to, string $body, callable $send): void
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
            'channel' => $channel, 'purpose' => 'admission', 'to' => $to, 'body' => $body,
            'status' => $status, 'error' => $error === null ? null : mb_substr($error, 0, 250),
        ]);
    }
}
