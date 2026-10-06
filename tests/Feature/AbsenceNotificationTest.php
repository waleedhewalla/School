<?php

namespace Tests\Feature;

use App\Actions\Attendance\RecordAttendance;
use App\Models\MessageLog;
use App\Support\Messaging\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class AbsenceNotificationTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    private function absent(array $data, int $studentIndex, string $code = 'A'): void
    {
        $this->inSchool($data['school'], fn () => app(RecordAttendance::class)->handle(
            $data['sectionA'], Carbon::parse('2026-09-02'), 0,
            [['student_id' => $data['students'][$studentIndex]->id, 'code' => $code]],
            $data['teacher'],
        ));
    }

    private function setUpSchool(): array
    {
        $school = $this->createSchool(attributes: ['name_ar' => 'مدارس النور', 'date_display' => 'gregorian']);
        $data = $this->seedSchoolData($school) + ['school' => $school];
        $this->inSchool($school, fn () => $data['students'][1]->guardians()->first()->update(['phone' => '0551234567']));

        return $data;
    }

    public function test_primary_guardian_gets_an_arabic_sms_and_it_is_logged(): void
    {
        $sent = [];
        $this->app->instance(SmsGateway::class, new class($sent) implements SmsGateway
        {
            public function __construct(private array &$sent) {}

            public function send(string $to, string $body): void
            {
                $this->sent[] = compact('to', 'body');
            }
        });

        $data = $this->setUpSchool();
        $this->absent($data, 1);

        $this->assertSame([[
            'to' => '966551234567',
            'body' => 'مدارس النور: نفيدكم بتسجيل حالة (غائب) لابنتكم سارة بتاريخ 2 سبتمبر 2026.',
        ]], $sent);

        $log = $this->inSchool($data['school'], fn () => MessageLog::query()->where('body', '!=', 'test')->sole());
        $this->assertSame(['sms', 'attendance', 'sent', $data['students'][1]->id], [$log->channel, $log->purpose, $log->status, $log->student_id]);
    }

    public function test_present_and_guardians_without_phone_send_nothing(): void
    {
        Http::fake();
        $data = $this->setUpSchool();

        $this->absent($data, 1, 'P');  // present: no alert
        $this->absent($data, 0, 'A');  // Ahmad's guardian has no phone

        $this->assertSame(0, $this->inSchool($data['school'], fn () => MessageLog::query()->where('body', '!=', 'test')->count()));
    }

    public function test_provider_failure_is_logged_not_thrown(): void
    {
        config(['services.sms.driver' => 'taqnyat', 'services.sms.taqnyat.token' => 't', 'services.sms.taqnyat.sender' => 'School']);
        $this->app->forgetInstance(SmsGateway::class);
        Http::fake(['api.taqnyat.sa/*' => Http::response(['message' => 'Invalid sender'], 400)]);

        $data = $this->setUpSchool();
        $this->absent($data, 1, 'L');

        $log = $this->inSchool($data['school'], fn () => MessageLog::query()->where('body', '!=', 'test')->sole());
        $this->assertSame('failed', $log->status);
        $this->assertSame('Taqnyat: Invalid sender', $log->error);
        Http::assertSent(fn ($request) => $request['recipients'] === ['966551234567'] && $request->hasHeader('Authorization', 'Bearer t'));
    }

    public function test_unifonic_request_shape(): void
    {
        config(['services.sms.driver' => 'unifonic', 'services.sms.unifonic.app_sid' => 'sid', 'services.sms.unifonic.sender_id' => 'School']);
        $this->app->forgetInstance(SmsGateway::class);
        Http::fake(['el.cloud.unifonic.com/*' => Http::response(['success' => true])]);

        $data = $this->setUpSchool();
        $this->absent($data, 1);

        Http::assertSent(fn ($request) => $request['AppSid'] === 'sid' && $request['Recipient'] === '966551234567');
        $this->assertSame('sent', $this->inSchool($data['school'], fn () => MessageLog::query()->where('body', '!=', 'test')->sole()->status));
    }
}
