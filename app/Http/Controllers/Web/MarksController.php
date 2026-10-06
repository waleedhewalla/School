<?php

namespace App\Http\Controllers\Web;

use App\Actions\Grades\RecordScores;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScore;
use App\Models\GradingScale;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Rules\ExistsInCurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Mark entry: one subject in one section for one term. */
class MarksController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $year = AcademicYear::query()->where('is_current', true)->first();
        $terms = $year ? $year->terms()->get() : collect();
        $term = $terms->firstWhere('id', $request->integer('term_id')) ?? $terms->firstWhere('marks_open', true) ?? $terms->first();

        // Managers see every section/subject; teachers only what they teach.
        $assignments = TeachingAssignment::query()
            ->with('section.gradeLevel', 'subject')
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->unless($user->can(Permission::GradesManage), fn ($q) => $q->whereHas('staffMember', fn ($s) => $s->where('user_id', $user->id)))
            ->get()
            ->sortBy(fn ($a) => [$a->section->grade_level_id, $a->section->name, $a->subject->sequence, $a->subject->code])
            ->values();

        $chosen = $assignments->first(fn ($a) => (int) $a->section_id === $request->integer('section_id') && (int) $a->subject_id === $request->integer('subject_id'));

        return Inertia::render('Marks/Entry', [
            'terms' => $terms->map(fn (Term $t) => ['id' => $t->id, 'name' => $t->name, 'marks_open' => $t->marks_open]),
            'classes' => $assignments->map(fn ($a) => [
                'section_id' => $a->section_id,
                'subject_id' => $a->subject_id,
                'label' => $a->section->gradeLevel->name.' / '.$a->section->name.' — '.$a->subject->name,
            ]),
            'filters' => ['term_id' => $term?->id, 'section_id' => $chosen?->section_id, 'subject_id' => $chosen?->subject_id],
            'sheet' => $term && $chosen ? $this->sheet($chosen->section, $term, $chosen->subject, $user) : null,
            'scale' => ($scale = GradingScale::forSchool($chosen?->section->gradeLevel->stage_id, $chosen?->section->grade_level_id)) ? [
                'pass_percent' => $scale->pass_percent,
                'bands' => $scale->bands->map(fn ($b) => ['min_percent' => $b->min_percent, 'label' => $b->label])->values(),
            ] : null,
        ]);
    }

    public function store(Request $request, RecordScores $record): RedirectResponse
    {
        $data = $request->validate([
            'term_id' => ['required', 'integer', ExistsInCurrentSchool::inTable('terms')],
            'section_id' => ['required', 'integer', ExistsInCurrentSchool::in('sections')],
            'subject_id' => ['required', 'integer', ExistsInCurrentSchool::in('subjects')],
            'rows' => ['present', 'array', 'max:200'],
            'rows.*.student_id' => ['required', 'integer', 'distinct'],
            'rows.*.scores' => ['present', 'array'],
        ]);

        $record->handle(
            Section::query()->findOrFail($data['section_id']),
            Term::query()->findOrFail($data['term_id']),
            Subject::query()->findOrFail($data['subject_id']),
            $data['rows'],
            $request->user(),
        );

        return back()->with('success', __('Marks saved.'));
    }

    private function sheet(Section $section, Term $term, Subject $subject, $user): array
    {
        $components = AssessmentComponent::query()->where('term_id', $term->id)
            ->where('grade_level_id', $section->grade_level_id)->where('subject_id', $subject->id)
            ->orderBy('sequence')->get();
        $students = Student::query()->inSection($section->id)->orderBy('family_name_ar')->orderBy('first_name_ar')->get();
        $scores = AssessmentScore::query()->whereIn('assessment_component_id', $components->modelKeys())
            ->whereIn('student_id', $students->modelKeys())->get()
            ->groupBy('student_id');

        return [
            'canEdit' => RecordScores::canEnter($section, $term, $subject, $user),
            'components' => $components->map(fn (AssessmentComponent $c) => ['id' => $c->id, 'name' => $c->name, 'max_score' => $c->max_score, 'weight' => $c->weight]),
            'students' => $students->map(fn (Student $s) => [
                'student_id' => $s->id,
                'name' => $s->name,
                'scores' => $components->mapWithKeys(function (AssessmentComponent $c) use ($scores, $s) {
                    $score = $scores->get($s->id)?->firstWhere('assessment_component_id', $c->id);

                    return [$c->id => $score?->is_absent ? 'absent' : $score?->score];
                }),
            ]),
        ];
    }
}
