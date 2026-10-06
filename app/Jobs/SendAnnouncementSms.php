<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\Guardian;
use App\Models\MessageLog;
use App\Models\School;
use App\Support\Messaging\SmsFailed;
use App\Support\Messaging\SmsGateway;
use App\Support\PhoneNumber;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Texts an announcement to primary guardians (of one section, or the whole
 * school). One SMS per phone number, so parents of siblings get one message.
 */
class SendAnnouncementSms implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $schoolId, public int $announcementId) {}

    public function handle(SmsGateway $sms, CurrentSchool $currentSchool): void
    {
        $school = School::query()->find($this->schoolId);
        if ($school === null || ! $school->isActive() || ! $school->notificationSettings()['channels']['sms']) {
            return;
        }

        $currentSchool->run($school, function (School $school) use ($sms) {
            $announcement = Announcement::query()->find($this->announcementId);
            if ($announcement === null) {
                return;
            }

            $body = mb_substr($school->name_ar.': '.$announcement->title.' — '.$announcement->body, 0, 300);

            Guardian::query()
                ->whereNotNull('phone')
                ->whereHas('students', function ($q) use ($announcement) {
                    $q->where('guardian_student.is_primary', true)->where('students.status', 'active');
                    if ($announcement->section_id) {
                        $q->inSection($announcement->section_id);
                    }
                })
                ->get()
                ->unique(fn (Guardian $g) => PhoneNumber::normalize($g->phone))
                ->each(function (Guardian $guardian) use ($sms, $body) {
                    $to = PhoneNumber::normalize($guardian->phone);
                    if ($to === null) {
                        return;
                    }

                    [$status, $error] = ['sent', null];
                    try {
                        $sms->send($to, $body);
                    } catch (SmsFailed $e) {
                        [$status, $error] = ['failed', mb_substr($e->getMessage(), 0, 250)];
                    }

                    MessageLog::query()->create([
                        'guardian_id' => $guardian->id, 'channel' => 'sms', 'purpose' => 'announcement',
                        'to' => $to, 'body' => $body, 'status' => $status, 'error' => $error,
                    ]);
                });
        });
    }
}
