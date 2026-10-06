<?php

namespace Tests\Feature;

use App\Enums\SchoolRole;
use App\Mail\InvitationMail;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScore;
use App\Models\Invitation;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Support\Quizzes\QuizGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class QuizzesTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    private array $d;

    private $school;

    private User $studentUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->school = $this->createSchool();
        $this->d = $this->seedSchoolData($this->school);
        $this->studentUser = $this->memberOf($this->school, SchoolRole::Student);
        $this->inSchool($this->school, fn () => $this->d['students'][0]->forceFill(['user_id' => $this->studentUser->id])->save());
    }

    /** Teacher builds a 3-question quiz (4 points) open now for section A. */
    private function buildQuiz(array $overrides = []): Quiz
    {
        $this->actingAs($this->d['teacher']);
        $this->post('/quizzes', $overrides + [
            'section_id' => $this->d['sectionA']->id, 'subject_id' => $this->d['subject']->id, 'title' => 'اختبار قصير ١',
            'opens_at' => '2026-09-03T10:00', 'closes_at' => '2026-09-05T23:00', 'time_limit_minutes' => 20,
        ])->assertRedirect();
        $quiz = $this->inSchool($this->school, fn () => Quiz::query()->latest('id')->first());

        $base = "/quizzes/{$quiz->id}/questions";
        $this->post($base, ['type' => 'single', 'body' => '٢ + ٣ = ؟', 'options' => ['4', '5', '6'], 'correct' => [1], 'points' => 1])->assertSessionHasNoErrors();
        $this->post($base, ['type' => 'multiple', 'body' => 'أعداد زوجية', 'options' => ['2', '3', '4'], 'correct' => [0, 2], 'points' => 2])->assertSessionHasNoErrors();
        $this->post($base, ['type' => 'short', 'body' => 'عاصمة المملكة؟', 'correct' => ['الرياض'], 'points' => 1])->assertSessionHasNoErrors();
        $this->put("/quizzes/{$quiz->id}", ['title' => $quiz->title, 'opens_at' => '2026-09-03T10:00', 'closes_at' => '2026-09-05T23:00', 'time_limit_minutes' => 20, 'published' => true, 'show_results' => true])
            ->assertSessionHasNoErrors();

        return $this->inSchool($this->school, fn () => $quiz->refresh()->load('questions'));
    }

    public function test_grader_normalises_arabic_short_answers(): void
    {
        $this->assertSame(QuizGrader::normalize('الرِّيَاض'), QuizGrader::normalize('الرياض'));
        $this->assertSame(QuizGrader::normalize('مَكّة المكرّمة'), QuizGrader::normalize('مكه المكرمه'));
        $this->assertSame(QuizGrader::normalize('إبراهيم'), QuizGrader::normalize('ابراهيم'));
    }

    public function test_teacher_builds_and_publishes_only_for_own_class(): void
    {
        $this->actingAs($this->d['teacher']);
        $this->post('/quizzes', ['section_id' => $this->d['sectionB']->id, 'subject_id' => $this->d['subject']->id, 'title' => 'x',
            'opens_at' => '2026-09-03T10:00', 'closes_at' => '2026-09-04T10:00'])->assertSessionHasErrors('subject_id');

        $this->post('/quizzes', ['section_id' => $this->d['sectionA']->id, 'subject_id' => $this->d['subject']->id, 'title' => 'فارغ',
            'opens_at' => '2026-09-03T10:00', 'closes_at' => '2026-09-04T10:00'])->assertRedirect();
        $empty = $this->inSchool($this->school, fn () => Quiz::query()->sole());
        $this->put("/quizzes/{$empty->id}", ['title' => 'فارغ', 'opens_at' => '2026-09-03T10:00', 'closes_at' => '2026-09-04T10:00', 'published' => true])
            ->assertSessionHasErrors('published');

        // Another teacher cannot open it.
        $this->actingAs($this->memberOf($this->school, SchoolRole::Teacher))->get("/quizzes/{$empty->id}/edit")->assertForbidden();
    }

    public function test_student_takes_the_quiz_answers_stay_hidden_and_score_reaches_the_mark_book(): void
    {
        $quiz = $this->buildQuiz();
        $q = $quiz->questions->keyBy('type');

        $this->actingAs($this->studentUser);
        $this->get('/my/quizzes')->assertInertia(fn (Assert $page) => $page->component('Quizzes/Mine')->where('children.0.quizzes.0.state', 'open'));
        $this->get("/my/quizzes/{$quiz->id}")->assertInertia(fn (Assert $page) => $page->component('Quizzes/Take')
            ->has('questions', 3)->missing('questions.0.correct'));

        $this->post("/my/quizzes/{$quiz->id}", ['answers' => [
            $q['single']->id => 1, $q['multiple']->id => [2, 0], $q['short']->id => 'الرِّياض',
        ]])->assertRedirect('/my/quizzes');
        $attempt = $this->inSchool($this->school, fn () => QuizAttempt::query()->sole());
        $this->assertSame(4.0, $attempt->score);

        // Second submission changes nothing; a new start sends them back.
        $this->post("/my/quizzes/{$quiz->id}", ['answers' => []]);
        $this->assertSame(4.0, $this->inSchool($this->school, fn () => $attempt->refresh()->score));
        $this->get("/my/quizzes/{$quiz->id}")->assertRedirect('/my/quizzes');

        // A student in another section cannot see it.
        $other = $this->memberOf($this->school, SchoolRole::Student);
        $this->actingAs($other)->get("/my/quizzes/{$quiz->id}")->assertNotFound();

        // Teacher copies scores into "classwork" (out of 20): 4/4 → 20.
        $classwork = $this->inSchool($this->school, fn () => AssessmentComponent::query()->where('max_score', 20)->first());
        $this->actingAs($this->d['teacher']);
        $this->get("/quizzes/{$quiz->id}/results")->assertInertia(fn (Assert $page) => $page->where('students', fn ($rows) => collect($rows)->firstWhere('id', $this->d['students'][0]->id)['status'] === 'submitted')->where('stats.0.correct_rate', 100));
        $this->post("/quizzes/{$quiz->id}/marks", ['assessment_component_id' => $classwork->id])->assertSessionHasNoErrors();
        $this->assertSame(20.0, (float) $this->inSchool($this->school, fn () => AssessmentScore::query()
            ->where('assessment_component_id', $classwork->id)->where('student_id', $this->d['students'][0]->id)->value('score')));

        // Questions are locked once students have answered.
        $this->post("/quizzes/{$quiz->id}/questions", ['type' => 'true_false', 'body' => 'x', 'correct' => [true], 'points' => 1])->assertSessionHasErrors('body');
    }

    public function test_answers_after_the_time_limit_are_not_counted(): void
    {
        $quiz = $this->buildQuiz();
        $this->actingAs($this->studentUser);
        $this->get("/my/quizzes/{$quiz->id}");

        $this->travel(25)->minutes();
        $this->post("/my/quizzes/{$quiz->id}", ['answers' => [$quiz->questions->first()->id => 1]]);
        $this->assertSame(0.0, $this->inSchool($this->school, fn () => QuizAttempt::query()->sole()->score));
    }

    public function test_guardian_and_student_portal_invitations_link_the_accounts(): void
    {
        Mail::fake();
        [$ahmad] = $this->d['students'];
        $guardian = $this->inSchool($this->school, fn () => $this->d['students'][1]->guardians()->first());
        $this->actingAs($this->memberOf($this->school, SchoolRole::Registrar));

        $this->post("/guardians/{$guardian->id}/invite", ['email' => 'mother@example.com'])->assertSessionHasNoErrors();
        $this->post("/students/{$ahmad->id}/invite", ['email' => 'ahmad@example.com'])->assertSessionHasErrors('email');

        $token = null;
        Mail::assertSent(InvitationMail::class, function ($mail) use (&$token) {
            $token = basename($mail->url);

            return $mail->hasTo('mother@example.com');
        });
        $invitation = $this->inSchool($this->school, fn () => Invitation::query()->where('email', 'mother@example.com')->sole());
        $this->assertSame($guardian->id, $invitation->guardian_id);

        // The mother accepts and lands on her own child.
        auth()->logout();
        $this->post("/invitations/{$token}", ['name' => 'أم سارة', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1'])->assertRedirect();
        $mother = User::query()->where('email', 'mother@example.com')->sole();
        $this->assertSame($mother->id, $this->inSchool($this->school, fn () => $guardian->refresh()->user_id));
        $this->get('/my/children')->assertInertia(fn (Assert $page) => $page->where('children.0.id', $this->d['students'][1]->id));
    }

    public function test_a_family_portal_cannot_be_sent_to_a_staff_login(): void
    {
        Mail::fake();
        $guardian = $this->inSchool($this->school, fn () => $this->d['students'][1]->guardians()->first());
        $registrar = $this->memberOf($this->school, SchoolRole::Registrar);

        $this->actingAs($registrar)->post("/guardians/{$guardian->id}/invite", ['email' => $registrar->email])->assertSessionHasErrors('email');
        Mail::assertNothingSent();
    }
}
