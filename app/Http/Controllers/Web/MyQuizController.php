<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Student;
use App\Support\Quizzes\QuizGrader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Students take quizzes for their section; guardians see their children's
 * results. Answers are marked on submission; after the deadline (plus a
 * short grace for a submission on its way) answers are no longer accepted.
 */
class MyQuizController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $students = Student::query()->where('user_id', $user->id)->orWhere(fn ($q) => $q->guardedBy($user))
            ->with('currentEnrollment')->get();

        return Inertia::render('Quizzes/Mine', [
            'children' => $students->map(function (Student $student) use ($user) {
                $sectionId = $student->currentEnrollment?->section_id;
                $quizzes = $sectionId ? Quiz::query()->with('subject')->withCount('questions')->where('section_id', $sectionId)->where('published', true)
                    ->where('opens_at', '>=', now()->subMonths(4))->orderByDesc('opens_at')->get() : collect();
                $attempts = QuizAttempt::query()->where('student_id', $student->id)->whereIn('quiz_id', $quizzes->pluck('id'))->get()->keyBy('quiz_id');

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'is_me' => (int) $student->user_id === (int) $user->id,
                    'quizzes' => $quizzes->map(fn (Quiz $q) => [
                        'id' => $q->id, 'title' => $q->title, 'subject' => $q->subject->name, 'questions' => $q->questions_count,
                        'opens_at' => $q->opens_at->toIso8601String(), 'closes_at' => $q->closes_at->toIso8601String(),
                        'time_limit' => $q->time_limit_minutes,
                        'state' => $attempts->get($q->id)?->submitted_at ? 'done' : ($q->isOpen() ? 'open' : ($q->opens_at->isFuture() ? 'upcoming' : 'missed')),
                        'score' => $q->show_results && $attempts->get($q->id)?->submitted_at ? $attempts[$q->id]->score : null,
                        'max' => $q->show_results && $attempts->get($q->id)?->submitted_at ? $attempts[$q->id]->max_score : null,
                    ]),
                ];
            }),
        ]);
    }

    public function take(Request $request, Quiz $quiz): Response|RedirectResponse
    {
        $student = $this->studentFor($request, $quiz);
        abort_unless($quiz->isOpen(), 403, __('quizzes.closed'));

        $attempt = QuizAttempt::query()->firstOrCreate(['quiz_id' => $quiz->id, 'student_id' => $student->id], ['started_at' => now()]);
        if ($attempt->submitted_at) {
            return redirect()->route('quizzes.mine')->with('success', __('quizzes.already_submitted'));
        }
        $quiz->load('questions', 'subject');

        return Inertia::render('Quizzes/Take', [
            'quiz' => ['id' => $quiz->id, 'title' => $quiz->title, 'instructions' => $quiz->instructions, 'subject' => $quiz->subject->name],
            'deadline' => $quiz->deadlineFor($attempt->started_at)->toIso8601String(),
            // Never send the right answers to the browser.
            'questions' => $quiz->questions->map(fn (QuizQuestion $q) => $q->only(['id', 'type', 'body', 'options', 'points'])),
        ]);
    }

    public function submit(Request $request, Quiz $quiz): RedirectResponse
    {
        $student = $this->studentFor($request, $quiz);
        $answers = $request->validate(['answers' => ['nullable', 'array', 'max:200']])['answers'] ?? [];

        DB::transaction(function () use ($quiz, $student, $answers) {
            $attempt = QuizAttempt::query()->where('quiz_id', $quiz->id)->where('student_id', $student->id)->lockForUpdate()->firstOrFail();
            if ($attempt->submitted_at) {
                return;
            }
            $quiz->load('questions');
            $late = now()->gt($quiz->deadlineFor($attempt->started_at)->copy()->addSeconds(Quiz::GRACE_SECONDS));
            $kept = $late ? [] : collect($answers)->only($quiz->questions->pluck('id')->map(fn ($id) => (string) $id)->all())
                ->map(fn ($a) => is_array($a) ? array_slice($a, 0, 10) : (is_string($a) ? mb_substr($a, 0, 500) : $a))->all();
            $grade = QuizGrader::grade($quiz->questions, $kept);

            $attempt->update(['answers' => $kept, 'submitted_at' => now(), 'score' => $grade['score'], 'max_score' => $grade['max']]);
        });

        return redirect()->route('quizzes.mine')->with('success', __('quizzes.submitted'));
    }

    /** The signed-in student, who must be in the quiz's section. */
    private function studentFor(Request $request, Quiz $quiz): Student
    {
        $student = Student::query()->where('user_id', $request->user()->id)->inSection($quiz->section_id)->first();
        abort_if($student === null || ! $quiz->published, 404);

        return $student;
    }
}
