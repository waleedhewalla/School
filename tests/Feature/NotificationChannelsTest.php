<?php

namespace Tests\Feature;

use App\Actions\Attendance\RecordAttendance;
use App\Enums\SchoolRole;
use App\Listeners\NotifyGuardiansOfAbsence;
use App\Mail\AttendanceAlert;
use App\Models\AttendanceCode;
use App\Models\MessageLog;
use App\Support\Messaging\WhatsAppGateway;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class NotificationChannelsTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    private function school(array $settings): array
    {
        $school = $this->createSchool(attributes: ['name_ar' => 'مدارس النور', 'notification_settings' => $settings]);
        $data = $this->seedSchoolData($school) + ['school' => $school];
        $this->inSchool($school, fn () => $data['students'][1]->guardians()->first()->update(['phone' => '0551234567', 'email' => 'parent@example.com']));

        return $data;
    }

    private function markAbsent(array $data, string $code = 'A'): void
    {
        $this->inSchool($data['school'], fn () => app(RecordAttendance::class)->handle(
            $data['sectionA'], Carbon::parse('2026-09-02'), 0,
            [['student_id' => $data['students'][1]->id, 'code' => $code]], $data['teacher'],
        ));
    }

    private function logs(array $data)
    {
        return $this->inSchool($data['school'], fn () => MessageLog::query()->where('body', '!=', 'test')->orderBy('channel')->get());
    }

    public function test_whatsapp_and_email_when_enabled(): void
    {
        Mail::fake();
        config(['services.whatsapp.driver' => 'meta', 'services.whatsapp.meta.token' => 'wa', 'services.whatsapp.meta.phone_number_id' => '123']);
        $this->app->forgetInstance(WhatsAppGateway::class);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'x']]])]);

        $data = $this->school(['channels' => ['sms' => false, 'whatsapp' => true, 'email' => true], 'quiet_hours' => ['enabled' => false]]);
        $this->markAbsent($data);

        $this->assertSame(['email', 'whatsapp'], $this->logs($data)->pluck('channel')->all());
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/123/messages')
            && $r['template']['name'] === 'attendance_alert'
            && $r['template']['language']['code'] === 'ar'
            && $r['template']['components'][0]['parameters'][1]['text'] === 'سارة');
        Mail::assertSent(AttendanceAlert::class, fn ($mail) => $mail->hasTo('parent@example.com') && str_contains($mail->body, 'لابنتكم سارة'));
    }

    public function test_codes_switched_off_do_not_alert(): void
    {
        $data = $this->school([]);
        $this->inSchool($data['school'], fn () => AttendanceCode::query()->where('code', 'L')->update(['notify_guardian' => false]));

        $this->markAbsent($data, 'L');

        $this->assertCount(0, $this->logs($data));
    }

    public function test_quiet_hours_delay(): void
    {
        $school = $this->createSchool(attributes: ['notification_settings' => ['quiet_hours' => ['enabled' => true, 'start' => '21:00', 'end' => '06:30']]]);
        $at = fn (string $time) => NotifyGuardiansOfAbsence::secondsUntilQuietHoursEnd($school, CarbonImmutable::parse("2026-09-02 $time", 'Asia/Riyadh'));

        $this->assertSame(0, $at('12:00'));
        $this->assertSame((9 * 60 + 30) * 60, $at('21:00'));
        $this->assertSame(30 * 60, $at('06:00'));
        $this->assertSame(0, $at('06:30'));

        $school->update(['notification_settings' => ['quiet_hours' => ['enabled' => false]]]);
        $this->assertSame(0, $at('23:00'));
    }

    public function test_admin_edits_notification_settings(): void
    {
        $school = $this->createSchool();
        $this->actingAs($this->memberOf($school, SchoolRole::SchoolAdmin));
        $lateId = $this->inSchool($school, fn () => AttendanceCode::query()->where('code', 'L')->value('id'));

        $this->get('/settings/notifications')->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Notifications')
            ->where('settings.channels.sms', true)
            ->has('codes', 4));

        $this->put('/settings/notifications', [
            'channels' => ['sms' => true, 'whatsapp' => true, 'email' => false],
            'quiet_hours' => ['enabled' => true, 'start' => '20:00', 'end' => '07:00'],
            'codes' => [['id' => $lateId, 'notify_guardian' => false]],
        ])->assertSessionHasNoErrors();

        $fresh = $school->fresh()->notificationSettings();
        $this->assertTrue($fresh['channels']['whatsapp']);
        $this->assertSame('20:00', $fresh['quiet_hours']['start']);
        $this->assertFalse($this->inSchool($school, fn () => AttendanceCode::query()->find($lateId)->notify_guardian));

        $this->actingAs($this->memberOf($school, SchoolRole::Registrar))->get('/settings/notifications')->assertForbidden();
    }
}
