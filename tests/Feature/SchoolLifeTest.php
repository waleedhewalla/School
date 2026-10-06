<?php

namespace Tests\Feature;

use App\Actions\Attendance\RecordAttendance;
use App\Actions\Students\AdmitStudent;
use App\Enums\SchoolRole;
use App\Models\AbsenceExcuse;
use App\Models\AttendanceRecord;
use App\Models\BehaviourCategory;
use App\Models\BehaviourIncident;
use App\Models\Homework;
use App\Models\LeaveRequest;
use App\Models\MessageLog;
use App\Models\User;
use App\Support\BehaviourScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class SchoolLifeTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    private array $d;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->school = $this->createSchool();
        $this->d = $this->seedSchoolData($this->school);

        // A guardian login for the first student, with a mobile for SMS.
        $this->parent = $this->memberOf($this->school, SchoolRole::Guardian);
        $this->inSchool($this->school, fn () => $this->d['students'][0]->guardians()->first()->forceFill(['user_id' => $this->parent->id, 'phone' => '0551112222'])->save());
    }

    private $school;

    public function test_teacher_posts_homework_for_own_subject_and_the_family_sees_it(): void
    {
        $this->actingAs($this->d['teacher']);
        $payload = ['section_id' => $this->d['sectionA']->id, 'subject_id' => $this->d['subject']->id, 'title' => 'تمارين صفحة 12', 'due_on' => '2026-09-06',
            'attachment' => UploadedFile::fake()->create('sheet.pdf', 20, 'application/pdf')];
        $this->post('/homework', $payload)->assertSessionHasNoErrors();
        $this->post('/homework', ['section_id' => $this->d['sectionB']->id] + $payload)->assertSessionHasErrors('subject_id');
        $this->post('/homework', ['due_on' => '2026-09-01'] + $payload)->assertSessionHasErrors('due_on');

        $homework = $this->inSchool($this->school, fn () => Homework::query()->sole());

        $this->actingAs($this->parent);
        $this->get('/my/homework')->assertInertia(fn (Assert $page) => $page->component('Homework/Mine')->where('children.0.homework.0.title', 'تمارين صفحة 12'));
        $this->get("/homework/{$homework->id}/attachment")->assertOk();
        $this->get('/homework')->assertForbidden();

        // A parent of a child in another section cannot download it.
        $otherParent = $this->memberOf($this->school, SchoolRole::Guardian);
        $this->actingAs($otherParent)->get("/homework/{$homework->id}/attachment")->assertNotFound();
    }

    public function test_behaviour_scores_notifications_and_teacher_limits(): void
    {
        [$minor, $major, $positive] = $this->inSchool($this->school, fn () => [
            BehaviourCategory::query()->where('degree', 1)->first(),
            BehaviourCategory::query()->where('degree', 4)->first(),
            BehaviourCategory::query()->where('kind', 'positive')->where('points', 3)->first(),
        ]);
        [$ahmad, $sara] = $this->d['students'];

        $this->actingAs($this->d['teacher']);
        $this->post('/behaviour', ['student_ids' => [$ahmad->id, $sara->id], 'behaviour_category_id' => $minor->id, 'occurred_on' => '2026-09-02'])->assertSessionHasNoErrors();
        $this->post('/behaviour', ['student_ids' => [$ahmad->id], 'behaviour_category_id' => $major->id, 'occurred_on' => '2026-09-03'])->assertSessionHasNoErrors();
        $this->post('/behaviour', ['student_ids' => [$ahmad->id], 'behaviour_category_id' => $positive->id, 'occurred_on' => '2026-09-03'])->assertSessionHasNoErrors();

        $scores = $this->inSchool($this->school, fn () => BehaviourScore::forStudents([$ahmad->id, $sara->id], $this->d['year']->id));
        $this->assertSame(100 - 1 - 10 + 3, $scores[$ahmad->id]['score']);
        $this->assertSame(99, $scores[$sara->id]['score']);

        // Degree 4 and the competition award tell the guardian; degree 1 does not.
        $this->inSchool($this->school, function () {
            $this->assertSame(2, MessageLog::query()->where('purpose', 'behaviour')->where('to', '966551112222')->count());
        });

        // A teacher cannot record for a section they do not teach.
        $outsider = $this->inSchool($this->school, fn () => app(AdmitStudent::class)->handle([
            'first_name_ar' => 'بدر', 'family_name_ar' => 'الحربي', 'gender' => 'male',
            'enrollment' => ['academic_year_id' => $this->d['year']->id, 'grade_level_id' => $this->d['grade']->id, 'section_id' => $this->d['sectionB']->id],
        ]));
        $this->post('/behaviour', ['student_ids' => [$outsider->id], 'behaviour_category_id' => $minor->id, 'occurred_on' => '2026-09-03'])->assertSessionHasErrors('student_ids');

        // A counsellor can, and manages categories.
        $this->actingAs($this->memberOf($this->school, SchoolRole::Counselor));
        $this->post('/behaviour', ['student_ids' => [$outsider->id], 'behaviour_category_id' => $minor->id, 'occurred_on' => '2026-09-03'])->assertSessionHasNoErrors();
        $this->delete("/behaviour/categories/{$minor->id}")->assertSessionHasErrors('category');
        $this->post('/behaviour/categories', ['name_ar' => 'الغش في الاختبار', 'kind' => 'negative', 'degree' => 3, 'points' => 3, 'notify_guardian' => true])->assertSessionHasNoErrors();

        // The family sees the record and score on the portal.
        $this->actingAs($this->parent)->get('/my/children')
            ->assertInertia(fn (Assert $page) => $page->where('children.0.behaviour.score', 92)->has('children.0.behaviour.recent', 3));
    }

    public function test_teacher_deletes_own_record_only_within_a_day(): void
    {
        $category = $this->inSchool($this->school, fn () => BehaviourCategory::query()->first());
        $this->actingAs($this->d['teacher']);
        $this->post('/behaviour', ['student_ids' => [$this->d['students'][0]->id], 'behaviour_category_id' => $category->id, 'occurred_on' => '2026-09-03']);
        $incident = $this->inSchool($this->school, fn () => BehaviourIncident::query()->sole());

        $this->travel(2)->days();
        $this->delete("/behaviour/{$incident->id}")->assertSessionHasErrors('incident');
        $this->travel(-2)->days();
        $this->delete("/behaviour/{$incident->id}")->assertSessionHasNoErrors();
    }

    public function test_approved_excuse_turns_absences_into_excused_now_and_later(): void
    {
        $ahmad = $this->d['students'][0];
        $register = fn (string $date) => $this->inSchool($this->school, fn () => app(RecordAttendance::class)->handle(
            $this->d['sectionA'], Carbon::parse($date), 0, [['student_id' => $ahmad->id, 'code' => 'A']], $this->d['teacher'],
        ));
        $register('2026-09-02');

        $this->actingAs($this->parent);
        $this->post('/my/excuses', ['student_id' => $ahmad->id, 'from_date' => '2026-09-02', 'to_date' => '2026-09-04', 'reason' => 'مراجعة طبية',
            'attachment' => UploadedFile::fake()->image('report.jpg')])->assertSessionHasNoErrors();
        $this->post('/my/excuses', ['student_id' => $this->d['students'][1]->id, 'from_date' => '2026-09-02', 'to_date' => '2026-09-02', 'reason' => 'x'])->assertForbidden();
        $this->post('/my/excuses', ['student_id' => $ahmad->id, 'from_date' => '2026-07-01', 'to_date' => '2026-07-02', 'reason' => 'x'])->assertSessionHasErrors('from_date');

        $excuse = $this->inSchool($this->school, fn () => AbsenceExcuse::query()->sole());
        $this->actingAs($this->d['teacher'])->post("/excuses/{$excuse->id}/review", ['status' => 'approved'])->assertForbidden();

        $this->actingAs($this->memberOf($this->school, SchoolRole::Registrar));
        $this->post("/excuses/{$excuse->id}/review", ['status' => 'rejected'])->assertSessionHasErrors('note');
        $this->post("/excuses/{$excuse->id}/review", ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->post("/excuses/{$excuse->id}/review", ['status' => 'rejected', 'note' => 'x'])->assertSessionHasErrors('status');
        $this->get("/excuses/{$excuse->id}/attachment")->assertOk();

        $register('2026-09-03');
        $kinds = $this->inSchool($this->school, fn () => AttendanceRecord::query()->with('code')->where('student_id', $ahmad->id)
            ->whereIn('date', ['2026-09-02', '2026-09-03'])->get()->map(fn ($r) => $r->code->kind->value)->unique()->values()->all());
        $this->assertSame(['excused'], $kinds);
    }

    public function test_staff_leave_request_and_review(): void
    {
        $this->actingAs($this->d['teacher']);
        $this->post('/my/leave', ['type' => 'sick', 'from_date' => '2026-09-06', 'to_date' => '2026-09-07'])->assertSessionHasNoErrors();
        $this->post('/my/leave', ['type' => 'emergency', 'from_date' => '2026-09-06', 'to_date' => '2026-09-06'])->assertSessionHasErrors('reason');
        $leave = $this->inSchool($this->school, fn () => LeaveRequest::query()->sole());
        $this->assertSame(2, $leave->days());

        $this->post("/leave/{$leave->id}/review", ['status' => 'approved'])->assertForbidden();

        // Someone without a staff record cannot ask.
        $this->actingAs($this->parent)->post('/my/leave', ['type' => 'annual', 'from_date' => '2026-09-06', 'to_date' => '2026-09-06'])->assertSessionHasErrors('type');

        $this->actingAs($this->memberOf($this->school, SchoolRole::Principal));
        $this->post("/leave/{$leave->id}/review", ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->travel(3)->days();
        $this->get('/leave')->assertInertia(fn (Assert $page) => $page->component('Requests/Leave')->where('awayToday.0.type', 'sick'));
    }
}
