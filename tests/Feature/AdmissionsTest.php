<?php

namespace Tests\Feature;

use App\Actions\Admissions\ChangeApplicationStatus;
use App\Enums\ApplicationStatus;
use App\Enums\SchoolRole;
use App\Models\AdmissionWindow;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Guardian;
use App\Models\MessageLog;
use App\Models\School;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class AdmissionsTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    private School $school;

    private array $d;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->school = $this->createSchool();
        $this->d = $this->seedSchoolData($this->school);

        // Grade 1 accepts children born in 2019–2020, up to 90 days younger by exception.
        $this->inSchool($this->school, fn () => $this->d['window']->update(['born_from' => '2019-01-01', 'born_to' => '2020-12-31', 'exception_days' => 90]));
    }

    private function form(array $overrides = []): array
    {
        return $this->applicationData($overrides) + ['admission_window_id' => $this->d['window']->id, 'consent' => true];
    }

    /** Submits through the public form and returns [application, token]. */
    private function apply(array $overrides = []): array
    {
        $response = $this->post("/apply/{$this->school->slug}", $this->form($overrides))->assertRedirect();
        $token = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));

        return [$this->inSchool($this->school, fn () => Application::findByToken($token)), $token];
    }

    private function staff(SchoolRole $role = SchoolRole::Registrar): void
    {
        $this->actingAs($this->memberOf($this->school, $role));
    }

    private function moveTo(Application $application, ApplicationStatus ...$statuses): Application
    {
        return $this->inSchool($this->school, function () use ($application, $statuses) {
            foreach ($statuses as $status) {
                app(ChangeApplicationStatus::class)->handle($application, $status, assessmentAt: $status === ApplicationStatus::AssessmentScheduled ? now()->addDay() : null);
            }

            return $application->refresh();
        });
    }

    public function test_public_form_lists_only_open_windows(): void
    {
        $this->inSchool($this->school, fn () => AdmissionWindow::query()->create([
            'academic_year_id' => $this->d['year']->id, 'grade_level_id' => $this->d['grade']->id + 1, 'seats' => 5,
            'opens_on' => now()->addDays(10), 'closes_on' => now()->addDays(40),
        ]));

        $this->get("/apply/{$this->school->slug}")
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page) => $page->component('Apply/Form')->has('windows', 1)->where('windows.0.id', $this->d['window']->id));
    }

    public function test_unknown_and_suspended_schools_are_not_found(): void
    {
        $this->get('/apply/no-such-school')->assertNotFound();

        $this->school->update(['status' => 'suspended']);
        $this->get("/apply/{$this->school->slug}")->assertNotFound();
    }

    public function test_family_applies_and_gets_a_private_link_by_sms(): void
    {
        [$application, $token] = $this->apply(['guardian_email' => 'family@example.com']);

        $this->assertSame(ApplicationStatus::Submitted, $application->status);
        $this->assertSame('1448-0002', $application->reference);
        $this->assertSame('966559998877', $application->guardian_phone);
        $this->assertNotSame($token, $application->token_hash);
        $this->assertSame($token, $application->token);

        $this->inSchool($this->school, function () use ($token) {
            $sms = MessageLog::query()->where('purpose', 'admission')->where('channel', 'sms')->sole();
            $this->assertStringContainsString($token, $sms->body);
            $this->assertStringContainsString('1448-0002', $sms->body);
            $this->assertSame(1, MessageLog::query()->where('purpose', 'admission')->where('channel', 'email')->count());
        });

        $this->get("/apply/{$this->school->slug}/status/{$token}")
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertInertia(fn (Assert $page) => $page->component('Apply/Status')
                ->where('application.status', 'submitted')
                ->where('documents', fn ($docs) => collect($docs)->pluck('type')->all() === ['birth_certificate', 'identity', 'photo', 'vaccination']));
    }

    public function test_consent_and_a_valid_mobile_are_required(): void
    {
        $this->post("/apply/{$this->school->slug}", ['consent' => false] + $this->form())->assertSessionHasErrors('consent');
        $this->post("/apply/{$this->school->slug}", $this->form(['guardian_phone' => '12345']))->assertSessionHasErrors('guardian_phone');
    }

    public function test_age_outside_the_range_is_refused_and_the_margin_is_flagged(): void
    {
        $this->post("/apply/{$this->school->slug}", $this->form(['date_of_birth' => '2018-06-01']))->assertSessionHasErrors('date_of_birth');
        $this->post("/apply/{$this->school->slug}", $this->form(['date_of_birth' => '2021-05-01']))->assertSessionHasErrors('date_of_birth');

        [$application] = $this->apply(['date_of_birth' => '2021-02-15']);
        $this->assertSame('exception', $application->age_check);
    }

    public function test_closed_window_and_another_schools_window_are_refused(): void
    {
        $other = $this->createSchool();
        $otherData = $this->seedSchoolData($other);

        $this->post("/apply/{$this->school->slug}", $this->form(['admission_window_id' => $otherData['window']->id]))
            ->assertSessionHasErrors('admission_window_id');

        $this->inSchool($this->school, fn () => $this->d['window']->update(['closes_on' => now()->subDay()]));
        $this->post("/apply/{$this->school->slug}", $this->form())->assertSessionHasErrors('admission_window_id');
    }

    public function test_the_same_child_cannot_apply_twice_in_a_year(): void
    {
        $id = $this->saudiId(4242);
        $this->apply(['national_id' => $id]);

        $this->post("/apply/{$this->school->slug}", $this->form(['national_id' => $id]))->assertSessionHasErrors('national_id');
    }

    public function test_sibling_priority_comes_from_the_guardian_already_in_the_school(): void
    {
        $this->inSchool($this->school, fn () => $this->d['students'][0]->guardians()->first()->update(['phone' => '0551112222']));

        [$sibling] = $this->apply(['guardian_phone' => '0551112222']);
        [$other] = $this->apply(['guardian_phone' => '0553334444', 'first_name_ar' => 'هند']);

        $this->assertTrue($sibling->has_sibling);
        $this->assertFalse($other->has_sibling);

        // Waitlist order: siblings first, then first come.
        $this->moveTo($other, ApplicationStatus::UnderReview, ApplicationStatus::Waitlisted);
        $this->moveTo($sibling, ApplicationStatus::UnderReview, ApplicationStatus::Waitlisted);
        $order = $this->inSchool($this->school, fn () => Application::query()->where('status', 'waitlisted')->waitlistOrder()->pluck('id')->all());
        $this->assertSame([$sibling->id, $other->id], $order);
    }

    public function test_status_page_needs_the_right_token_in_the_right_school(): void
    {
        [, $token] = $this->apply();
        $other = $this->createSchool();

        $this->get("/apply/{$this->school->slug}/status/".str_repeat('a', 40))->assertNotFound();
        $this->get("/apply/{$other->slug}/status/{$token}")->assertNotFound();
    }

    public function test_family_uploads_required_documents_only(): void
    {
        [$application, $token] = $this->apply();
        $url = "/apply/{$this->school->slug}/status/{$token}/documents";

        $this->post($url, ['type' => 'photo', 'file' => UploadedFile::fake()->image('me.jpg')])->assertSessionHasNoErrors();
        $this->post($url, ['type' => 'financial_clearance', 'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('type');
        $this->post($url, ['type' => 'identity', 'file' => UploadedFile::fake()->create('x.exe', 10)])->assertSessionHasErrors('file');

        $document = $this->inSchool($this->school, fn () => $application->documents()->sole());
        Storage::disk('local')->assertExists($document->path);
        $this->assertStringStartsWith("admissions/{$this->school->id}/{$application->id}/", $document->path);

        // Replacing a pending document removes the old file; an accepted one is locked.
        $this->post($url, ['type' => 'photo', 'file' => UploadedFile::fake()->image('new.jpg')])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($document->path);

        $this->inSchool($this->school, fn () => $application->documents()->update(['status' => ApplicationDocument::ACCEPTED]));
        $this->post($url, ['type' => 'photo', 'file' => UploadedFile::fake()->image('again.jpg')])->assertSessionHasErrors('file');
    }

    public function test_family_accepts_an_offer_or_withdraws(): void
    {
        [$application, $token] = $this->apply();
        $url = "/apply/{$this->school->slug}/status/{$token}/respond";

        $this->post($url, ['action' => 'accept'])->assertSessionHasErrors('status');

        $this->moveTo($application, ApplicationStatus::UnderReview, ApplicationStatus::Offered);
        $this->post($url, ['action' => 'accept'])->assertSessionHas('success');
        $this->assertSame(ApplicationStatus::Accepted, $this->inSchool($this->school, fn () => $application->refresh()->status));

        $this->post($url, ['action' => 'withdraw'])->assertSessionHas('success');
        $this->assertSame(ApplicationStatus::Withdrawn, $this->inSchool($this->school, fn () => $application->refresh()->status));
    }

    public function test_staff_pipeline_checks_transitions_seats_and_logs_history(): void
    {
        $this->staff();
        $applications = collect(range(1, 3))->map(fn ($i) => $this->apply(['first_name_ar' => "طالب{$i}", 'guardian_phone' => "055000000{$i}"])[0]);

        // Cannot jump straight from submitted to offered.
        $this->post("/admissions/{$applications[0]->id}/status", ['status' => 'offered'])->assertSessionHasErrors('status');

        // An interview needs a time.
        $this->post("/admissions/{$applications[0]->id}/status", ['status' => 'under_review'])->assertSessionHasNoErrors();
        $this->post("/admissions/{$applications[0]->id}/status", ['status' => 'assessment_scheduled'])->assertSessionHasErrors('assessment_at');
        $this->post("/admissions/{$applications[0]->id}/status", ['status' => 'assessment_scheduled', 'assessment_at' => '2026-09-10T09:30'])->assertSessionHasNoErrors();
        $this->assertSame('2026-09-10 06:30', $this->inSchool($this->school, fn () => $applications[0]->refresh()->assessment_at->format('Y-m-d H:i')));

        // The window has two seats; the third offer is refused.
        $this->moveTo($applications[0], ApplicationStatus::Assessed, ApplicationStatus::Offered);
        $this->moveTo($applications[1], ApplicationStatus::UnderReview, ApplicationStatus::Offered);
        $this->moveTo($applications[2], ApplicationStatus::UnderReview);
        $this->post("/admissions/{$applications[2]->id}/status", ['status' => 'offered'])->assertSessionHasErrors('status');
        $this->post("/admissions/{$applications[2]->id}/status", ['status' => 'waitlisted', 'note' => 'لا مقاعد'])->assertSessionHasNoErrors();

        // Withdrawing an offer frees the seat for the waitlist.
        $this->moveTo($applications[1], ApplicationStatus::Withdrawn);
        $this->post("/admissions/{$applications[2]->id}/status", ['status' => 'offered'])->assertSessionHasNoErrors();

        $this->get("/admissions/{$applications[2]->id}")
            ->assertInertia(fn (Assert $page) => $page->component('Admissions/Show')
                ->where('application.status', 'offered')
                ->has('events', 4)
                ->where('events.1.note', 'لا مقاعد'));

        $this->inSchool($this->school, function () {
            $this->assertSame(1, MessageLog::query()->where('channel', 'sms')->where('to', '966550000003')->where('body', 'like', '%قائمة الانتظار%')->count());
        });
    }

    public function test_enrolling_an_accepted_applicant_creates_the_student_with_siblings(): void
    {
        $this->inSchool($this->school, fn () => $this->d['students'][0]->guardians()->first()->update(['phone' => '0551112222']));
        [$application] = $this->apply(['guardian_phone' => '0551112222', 'national_id' => $this->saudiId(777)]);
        $this->staff();

        $this->post("/admissions/{$application->id}/enrol", ['section_id' => $this->d['sectionB']->id])->assertSessionHasErrors('status');

        $this->moveTo($application, ApplicationStatus::UnderReview, ApplicationStatus::Offered, ApplicationStatus::Accepted);
        $this->post("/admissions/{$application->id}/enrol", ['section_id' => $this->d['sectionB']->id])->assertRedirect();

        $this->inSchool($this->school, function () use ($application) {
            $application->refresh();
            $student = Student::query()->findOrFail($application->student_id);
            $this->assertSame(ApplicationStatus::Enrolled, $application->status);
            $this->assertSame($this->d['students'][0]->family_id, $student->family_id);
            $this->assertSame(1, Guardian::query()->where('phone', 'like', '%551112222')->count());
            $this->assertSame($this->d['sectionB']->id, $student->currentEnrollment->section_id);
            $this->assertSame($this->d['window']->grade_level_id, $student->currentEnrollment->grade_level_id);
        });
    }

    public function test_enrolment_refuses_a_child_already_registered(): void
    {
        $id = $this->saudiId(888);
        $this->inSchool($this->school, fn () => $this->d['students'][1]->update(['national_id' => $id]));
        [$application] = $this->apply(['national_id' => $id]);
        $this->moveTo($application, ApplicationStatus::UnderReview, ApplicationStatus::Offered, ApplicationStatus::Accepted);
        $this->staff();

        $this->get("/admissions/{$application->id}")
            ->assertInertia(fn (Assert $page) => $page->where('duplicates.0.kind', 'student'));
        $this->post("/admissions/{$application->id}/enrol")->assertSessionHasErrors('national_id');
    }

    public function test_staff_review_documents_and_download_them(): void
    {
        [$application, $token] = $this->apply();
        $this->post("/apply/{$this->school->slug}/status/{$token}/documents", ['type' => 'photo', 'file' => UploadedFile::fake()->image('me.jpg')]);
        $document = $this->inSchool($this->school, fn () => $application->documents()->sole());
        $this->staff();

        $this->patch("/admission-documents/{$document->id}", ['status' => 'rejected'])->assertSessionHasErrors('reason');
        $this->patch("/admission-documents/{$document->id}", ['status' => 'rejected', 'reason' => 'الصورة غير واضحة'])->assertSessionHasNoErrors();
        $this->get("/admission-documents/{$document->id}")->assertOk()->assertDownload('me.jpg');

        $this->get("/apply/{$this->school->slug}/status/{$token}")
            ->assertInertia(fn (Assert $page) => $page->where('documents.2.status', 'rejected')->where('documents.2.reason', 'الصورة غير واضحة'));
    }

    public function test_only_admissions_staff_see_applications_of_their_school(): void
    {
        $this->actingAs($this->memberOf($this->school, SchoolRole::Teacher));
        $this->get('/admissions')->assertForbidden();

        $other = $this->createSchool();
        $foreign = $this->seedSchoolData($other)['application'];

        $this->staff(SchoolRole::Principal);
        $this->get('/admissions')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admissions/Index')->has('applications.data', 1));
        $this->get("/admissions/{$foreign->id}")->assertNotFound();
        $this->post("/admissions/{$foreign->id}/status", ['status' => 'under_review'])->assertNotFound();
    }

    public function test_windows_are_unique_per_grade_and_year_and_kept_while_used(): void
    {
        $this->staff();
        $payload = ['academic_year_id' => $this->d['year']->id, 'grade_level_id' => $this->d['grade']->id, 'seats' => 10, 'opens_on' => '2026-09-01', 'closes_on' => '2026-10-01', 'exception_days' => 0];

        $this->post('/admissions/windows', $payload)->assertSessionHasErrors('grade_level_id');
        $this->post('/admissions/windows', ['grade_level_id' => $this->d['grade']->id + 1] + $payload)->assertSessionHasNoErrors();
        $this->post('/admissions/windows', ['grade_level_id' => $this->d['grade']->id + 2, 'closes_on' => '2026-08-01'] + $payload)->assertSessionHasErrors('closes_on');

        $this->delete("/admissions/windows/{$this->d['window']->id}")->assertSessionHasErrors('window');
        $this->get('/admissions/windows')->assertInertia(fn (Assert $page) => $page->component('Admissions/Windows')->has('windows', 2));
    }

    public function test_documents_of_closed_applications_are_pruned_after_the_retention_period(): void
    {
        [$rejected, $token] = $this->apply();
        $this->post("/apply/{$this->school->slug}/status/{$token}/documents", ['type' => 'photo', 'file' => UploadedFile::fake()->image('me.jpg')]);
        [$open, $openToken] = $this->apply(['first_name_ar' => 'هند', 'guardian_phone' => '0553334444']);
        $this->post("/apply/{$this->school->slug}/status/{$openToken}/documents", ['type' => 'photo', 'file' => UploadedFile::fake()->image('me.jpg')]);
        $this->moveTo($rejected, ApplicationStatus::Rejected);

        $this->artisan('madrasa:prune-admission-documents')->assertSuccessful();
        $this->assertSame(2, $this->inSchool($this->school, fn () => ApplicationDocument::query()->whereIn('application_id', [$rejected->id, $open->id])->count()));

        $this->travel(91)->days();
        $this->artisan('madrasa:prune-admission-documents')->assertSuccessful();
        $this->inSchool($this->school, function () use ($rejected, $open) {
            $this->assertSame(0, $rejected->documents()->count());
            $this->assertSame(1, $open->documents()->count());
        });
    }

    public function test_changing_status_outside_the_map_throws(): void
    {
        $this->expectException(ValidationException::class);
        $this->inSchool($this->school, fn () => app(ChangeApplicationStatus::class)->handle($this->d['application'], ApplicationStatus::Enrolled));
    }
}
