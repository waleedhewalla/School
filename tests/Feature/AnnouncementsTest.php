<?php

namespace Tests\Feature;

use App\Actions\Students\AdmitStudent;
use App\Enums\SchoolRole;
use App\Models\Announcement;
use App\Models\MessageLog;
use App\Support\Messaging\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class AnnouncementsTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    private function setUpSchool(): array
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school) + ['school' => $school];

        // Both section-A students share one guardian mobile (siblings); a third student sits in section B.
        $this->inSchool($school, function () use ($data) {
            foreach ($data['students'] as $student) {
                $student->guardians()->first()->update(['phone' => '0551112222']);
            }
            app(AdmitStudent::class)->handle([
                'first_name_ar' => 'بدر', 'family_name_ar' => 'الحربي', 'gender' => 'male',
                'guardians' => [['name_ar' => 'ولي أمر بدر', 'phone' => '0553334444', 'relationship' => 'father', 'is_primary' => true]],
                'enrollment' => ['academic_year_id' => $data['year']->id, 'grade_level_id' => $data['grade']->id, 'section_id' => $data['sectionB']->id],
            ]);
        });

        return $data;
    }

    public function test_section_announcement_with_sms_reaches_that_sections_guardians_once(): void
    {
        $sent = [];
        $this->app->instance(SmsGateway::class, new class($sent) implements SmsGateway
        {
            public function __construct(private array &$sent) {}

            public function send(string $to, string $body): void
            {
                $this->sent[] = $to;
            }
        });

        $d = $this->setUpSchool();
        $this->actingAs($this->memberOf($d['school'], SchoolRole::Principal));

        $this->post('/announcements', [
            'title' => 'رحلة مدرسية', 'body' => 'رحلة يوم الخميس', 'audience' => 'guardians',
            'section_id' => $d['sectionA']->id, 'send_sms' => true,
        ])->assertSessionHas('success');

        $this->assertSame(['966551112222'], $sent);
        $this->assertSame(1, $this->inSchool($d['school'], fn () => MessageLog::query()->where('purpose', 'announcement')->count()));
    }

    public function test_guardians_see_school_wide_and_their_sections_only(): void
    {
        $d = $this->setUpSchool();
        $this->inSchool($d['school'], function () use ($d) {
            Announcement::query()->create(['audience' => 'guardians', 'section_id' => $d['sectionA']->id, 'title' => 'لفصل أ', 'body' => '.', 'published_at' => now()]);
            Announcement::query()->create(['audience' => 'guardians', 'section_id' => $d['sectionB']->id, 'title' => 'لفصل ب', 'body' => '.', 'published_at' => now()]);
            Announcement::query()->create(['audience' => 'staff', 'title' => 'اجتماع المعلمين', 'body' => '.', 'published_at' => now()]);
        });

        $parent = $this->memberOf($d['school'], SchoolRole::Guardian);
        $this->inSchool($d['school'], fn () => $d['students'][0]->guardians()->first()->user()->associate($parent)->save());

        $this->actingAs($parent)->get('/my/children')->assertInertia(fn (Assert $page) => $page
            ->where('announcements', fn ($list) => collect($list)->pluck('title')->sort()->values()->all() === ['ترحيب', 'لفصل أ']));
        $this->get('/announcements')->assertForbidden();

        $this->actingAs($d['teacher'])->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('announcements', fn ($list) => collect($list)->pluck('title')->sort()->values()->all() === ['اجتماع المعلمين', 'ترحيب']));
    }

    public function test_only_managers_post_and_staff_announcements_cannot_go_by_sms(): void
    {
        $d = $this->setUpSchool();

        $this->actingAs($d['teacher'])->get('/announcements')->assertInertia(fn (Assert $page) => $page->where('canManage', false));
        $this->post('/announcements', ['title' => 'x', 'body' => 'y', 'audience' => 'guardians'])->assertForbidden();

        $this->actingAs($this->memberOf($d['school'], SchoolRole::SchoolAdmin));
        $this->post('/announcements', ['title' => 'x', 'body' => 'y', 'audience' => 'staff', 'send_sms' => true])->assertSessionHasErrors('send_sms');
    }
}
