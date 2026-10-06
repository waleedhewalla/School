<?php

namespace App\Http\Controllers\Web;

use App\Actions\Grades\RecordScores;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AssessmentComponent;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Rules\ExistsInCurrentSchool;
use App\Support\Quizzes\QuizGrader;
use App\Support\Teaching;
use App\Support\Tenancy\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Teachers build online quizzes for their classes, see results and send scores to the mark book. */
class QuizController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::HomeworkAssign);
        $user = $request->user();
        $all = $user->can(Permission::AcademicStructureManage);
        $year = AcademicYear::query()->where('is_current', true)->first();

        return Inertia::render('Quizzes/Index', [
            'quizzes' => Quiz::query()->with('section.gradeLevel', 'subject')->withCount(['questions', 'attempts' => fn ($q) => $q->whereNotNull('submitted_at')])
                ->when(! $all, fn ($q) => $q->whereIn('section_id', Teaching::sectionIdsOf($user)))
                ->latest('opens_at')->limit(100)->get()
                ->map(fn (Quiz $q) => [
                    'id' => $q->id, 'title' => $q->title, 'subject' => $q->subject->name,
                    'section' => $q->section->gradeLevel->name.' / '.$q->section->name,
                    'opens_at' => $q->opens_at->toIso8601String(), 'closes_at' => $q->closes_at->toIso8601String(),
                    'published' => $q->published, 'questions' => $q->questions_count, 'submitted' => $q->attempts_count,
                ]),
            'choices' => $this->choices($request, $year),
            'subjects' => Subject::query()->ordered()->get()->map(fn (Subject $s) => ['id' => $s->id, 'name' => $s->name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $quiz = Quiz::query()->create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('quizzes.edit', $quiz)->with('success', __('quizzes.created'));
    }

    public function edit(Request $request, Quiz $quiz): Response
    {
        $this->authorizeQuiz($request, $quiz);
        $quiz->load('section.gradeLevel', 'subject', 'questions');

        return Inertia::render('Quizzes/Edit', [
            'quiz' => [
                ...$quiz->only(['id', 'section_id', 'subject_id', 'title', 'instructions', 'time_limit_minutes', 'published', 'show_results']),
                'opens_at' => $quiz->opens_at->setTimezone($this->tz())->format('Y-m-d\TH:i'),
                'closes_at' => $quiz->closes_at->setTimezone($this->tz())->format('Y-m-d\TH:i'),
                'section' => $quiz->section->gradeLevel->name.' / '.$quiz->section->name,
                'subject' => $quiz->subject->name,
                'has_attempts' => $quiz->attempts()->exists(),
            ],
            'questions' => $quiz->questions->map(fn (QuizQuestion $q) => $q->only(['id', 'type', 'body', 'options', 'correct', 'points', 'sequence'])),
        ]);
    }

    public function update(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeQuiz($request, $quiz);
        $data = $this->validated($request, $quiz);
        if (($data['published'] ?? false) && ! $quiz->questions()->exists()) {
            throw ValidationException::withMessages(['published' => __('quizzes.no_questions')]);
        }
        $quiz->update($data);

        return back()->with('success', __('Changes saved.'));
    }

    public function destroy(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeQuiz($request, $quiz);
        $quiz->delete();

        return redirect()->route('quizzes.index')->with('success', __('Deleted.'));
    }

    public function saveQuestion(Request $request, Quiz $quiz, ?QuizQuestion $question = null): RedirectResponse
    {
        $this->authorizeQuiz($request, $quiz);
        abort_if($question && $question->quiz_id !== $quiz->id, 404);
        if ($quiz->attempts()->exists()) {
            throw ValidationException::withMessages(['body' => __('quizzes.locked')]);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(QuizQuestion::TYPES)],
            'body' => ['required', 'string', 'max:2000'],
            'options' => ['nullable', 'array', 'max:8', Rule::requiredIf(in_array($request->input('type'), ['single', 'multiple'], true))],
            'options.*' => ['required', 'string', 'max:300'],
            'correct' => ['required', 'array', 'min:1', 'max:8'],
            'points' => ['required', 'numeric', 'min:0.5', 'max:100'],
        ]);
        $data['options'] = in_array($data['type'], ['single', 'multiple'], true) ? array_values($data['options']) : null;
        $data['correct'] = $this->correctAnswers($data);

        $question
            ? $question->update($data)
            : $quiz->questions()->create($data + ['sequence' => $quiz->questions()->max('sequence') + 1]);

        return back()->with('success', __('Changes saved.'));
    }

    public function destroyQuestion(Request $request, Quiz $quiz, QuizQuestion $question): RedirectResponse
    {
        $this->authorizeQuiz($request, $quiz);
        abort_if($question->quiz_id !== $quiz->id, 404);
        if ($quiz->attempts()->exists()) {
            return back()->withErrors(['body' => __('quizzes.locked')]);
        }
        $question->delete();

        return back()->with('success', __('Deleted.'));
    }

    public function results(Request $request, Quiz $quiz): Response
    {
        $this->authorizeQuiz($request, $quiz);
        $quiz->load('section.gradeLevel', 'subject', 'questions');
        $attempts = QuizAttempt::query()->with('student')->where('quiz_id', $quiz->id)->get()->keyBy('student_id');
        $roster = Student::query()->inSection($quiz->section_id)->orderBy('first_name_ar')->get();

        // Share of students who got each question right.
        $stats = $quiz->questions->map(function (QuizQuestion $q) use ($attempts) {
            $done = $attempts->whereNotNull('submitted_at');
            $right = $done->filter(fn (QuizAttempt $a) => QuizGrader::isCorrect($q, $a->answers[$q->id] ?? null))->count();

            return ['id' => $q->id, 'body' => $q->body, 'correct_rate' => $done->count() ? round($right / $done->count() * 100) : null];
        });

        return Inertia::render('Quizzes/Results', [
            'quiz' => ['id' => $quiz->id, 'title' => $quiz->title, 'section' => $quiz->section->gradeLevel->name.' / '.$quiz->section->name,
                'subject' => $quiz->subject->name, 'max' => $quiz->maxScore()],
            'students' => $roster->map(fn (Student $s) => [
                'id' => $s->id, 'name' => $s->name,
                'score' => $attempts->get($s->id)?->submitted_at ? $attempts[$s->id]->score : null,
                'status' => ! $attempts->has($s->id) ? 'not_started' : ($attempts[$s->id]->submitted_at ? 'submitted' : 'in_progress'),
            ]),
            'stats' => $stats,
            'components' => AssessmentComponent::query()->with('term')
                ->where('grade_level_id', $quiz->section->grade_level_id)->where('subject_id', $quiz->subject_id)
                ->whereHas('term.academicYear', fn ($q) => $q->where('is_current', true))
                ->orderBy('term_id')->orderBy('sequence')->get()
                ->map(fn (AssessmentComponent $c) => ['id' => $c->id, 'label' => $c->term->name.' — '.$c->name.' (/'.($c->max_score + 0).')']),
        ]);
    }

    /** Copies the quiz scores, scaled to the component's maximum, into the mark book. */
    public function sendToMarks(Request $request, Quiz $quiz, RecordScores $record): RedirectResponse
    {
        $this->authorizeQuiz($request, $quiz);
        $data = $request->validate(['assessment_component_id' => ['required', 'integer', ExistsInCurrentSchool::inTable('assessment_components')]]);
        $component = AssessmentComponent::query()->with('term')->findOrFail($data['assessment_component_id']);
        $quiz->load('section', 'questions');
        abort_unless((int) $component->subject_id === (int) $quiz->subject_id && (int) $component->grade_level_id === (int) $quiz->section->grade_level_id, 422);

        $max = $quiz->maxScore();
        $rows = QuizAttempt::query()->where('quiz_id', $quiz->id)->whereNotNull('submitted_at')->get()
            ->map(fn (QuizAttempt $a) => ['student_id' => $a->student_id, 'scores' => [
                $component->id => $max > 0 ? round($a->score / $max * $component->max_score, 2) : 0,
            ]])->values()->all();

        $record->handle($quiz->section, $component->term, $quiz->subject, $rows, $request->user());

        return back()->with('success', __('quizzes.sent_to_marks', ['count' => count($rows)]));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Quiz $quiz = null): array
    {
        $data = $request->validate([
            'section_id' => [$quiz ? 'prohibited' : 'required', 'integer', ExistsInCurrentSchool::in('sections')],
            'subject_id' => [$quiz ? 'prohibited' : 'required', 'integer', ExistsInCurrentSchool::in('subjects')],
            'title' => ['required', 'string', 'max:200'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'opens_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'closes_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:opens_at'],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:300'],
            'published' => ['boolean'],
            'show_results' => ['boolean'],
        ]);

        $user = $request->user();
        if (! $quiz && ! $user->can(Permission::AcademicStructureManage) && ! Teaching::teachesSubject($user, $data['section_id'], $data['subject_id'])) {
            throw ValidationException::withMessages(['subject_id' => __('homework.not_your_subject')]);
        }
        foreach (['opens_at', 'closes_at'] as $key) {
            $data[$key] = CarbonImmutable::parse($data[$key], $this->tz())->utc();
        }

        return $data;
    }

    /** @return list<int|bool|string> */
    private function correctAnswers(array $data): array
    {
        $correct = array_values($data['correct']);

        return match ($data['type']) {
            'single' => [(int) $correct[0]],
            'multiple' => array_values(array_unique(array_map('intval', $correct))),
            'true_false' => [filter_var($correct[0], FILTER_VALIDATE_BOOLEAN)],
            'short' => array_values(array_filter(array_map(fn ($a) => mb_substr(trim((string) $a), 0, 200), $correct))),
        } ?: throw ValidationException::withMessages(['correct' => __('quizzes.correct_required')]);
    }

    private function authorizeQuiz(Request $request, Quiz $quiz): void
    {
        $user = $request->user();
        abort_unless($user->can(Permission::AcademicStructureManage)
            || ($user->can(Permission::HomeworkAssign) && Teaching::teachesSubject($user, $quiz->section_id, $quiz->subject_id)), 403);
    }

    private function choices(Request $request, ?AcademicYear $year)
    {
        $user = $request->user();
        if ($user->can(Permission::AcademicStructureManage)) {
            return Section::query()->with('gradeLevel')->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
                ->orderBy('grade_level_id')->orderBy('name')->get()
                ->map(fn (Section $s) => ['section_id' => $s->id, 'label' => $s->gradeLevel->name.' / '.$s->name, 'subjects' => null]);
        }

        return Teaching::assignmentsOf($user)->groupBy('section_id')->map(fn ($list) => [
            'section_id' => $list->first()->section_id,
            'label' => $list->first()->section->gradeLevel->name.' / '.$list->first()->section->name,
            'subjects' => $list->pluck('subject_id')->all(),
        ])->values();
    }

    private function tz(): string
    {
        return app(CurrentSchool::class)->get()->timezone;
    }
}
