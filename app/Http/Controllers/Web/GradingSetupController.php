<?php

namespace App\Http\Controllers\Web;

use App\Actions\Grades\SaveAssessmentComponents;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AssessmentComponent;
use App\Models\GradeLevel;
use App\Models\GradingScale;
use App\Models\Stage;
use App\Models\Subject;
use App\Models\Term;
use App\Rules\ExistsInCurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** How each subject is assessed per grade and term, and the school's grading scale. */
class GradingSetupController extends Controller
{
    public function index(Request $request): Response
    {
        $terms = $this->currentTerms();
        $term = $terms->firstWhere('id', $request->integer('term_id')) ?? $terms->first();
        $grades = $this->grades();
        $grade = $grades->firstWhere('id', $request->integer('grade_level_id'));

        $components = $term && $grade ? AssessmentComponent::query()
            ->where('term_id', $term->id)->where('grade_level_id', $grade->id)
            ->orderBy('sequence')->get()->groupBy('subject_id') : collect();

        return Inertia::render('Grading/Setup', [
            'terms' => $terms->map(fn (Term $t) => ['id' => $t->id, 'name' => $t->name, 'marks_open' => $t->marks_open, 'published' => $t->resultsPublished()]),
            'grades' => $grades->map(fn (GradeLevel $g) => ['id' => $g->id, 'name' => $g->name]),
            'filters' => ['term_id' => $term?->id, 'grade_level_id' => $grade?->id],
            'subjects' => $grade ? Subject::query()->ordered()->get()->map(fn (Subject $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'components' => ($components[$s->id] ?? collect())->map(fn (AssessmentComponent $c) => [
                    'id' => $c->id, 'name_ar' => $c->name_ar, 'name_en' => $c->name_en, 'max_score' => $c->max_score, 'weight' => $c->weight,
                ])->values(),
            ]) : [],
        ]);
    }

    public function save(Request $request, SaveAssessmentComponents $save): RedirectResponse
    {
        $data = $request->validate([
            'term_id' => ['required', 'integer', ExistsInCurrentSchool::inTable('terms')],
            'grade_level_id' => ['required', 'integer', ExistsInCurrentSchool::inTable('grade_levels')],
            'subject_id' => ['required', 'integer', ExistsInCurrentSchool::in('subjects')],
        ] + SaveAssessmentComponents::rules());

        $save->handle(
            Term::query()->findOrFail($data['term_id']),
            GradeLevel::query()->findOrFail($data['grade_level_id']),
            Subject::query()->findOrFail($data['subject_id']),
            $data['components'],
        );

        return back()->with('success', __('Changes saved.'));
    }

    /** Copy one subject's components to every subject of the grade that has none yet. */
    public function copy(Request $request, SaveAssessmentComponents $save): RedirectResponse
    {
        $data = $request->validate([
            'term_id' => ['required', 'integer', ExistsInCurrentSchool::inTable('terms')],
            'grade_level_id' => ['required', 'integer', ExistsInCurrentSchool::inTable('grade_levels')],
            'subject_id' => ['required', 'integer', ExistsInCurrentSchool::in('subjects')],
        ]);

        $source = AssessmentComponent::query()->where('term_id', $data['term_id'])
            ->where('grade_level_id', $data['grade_level_id'])->where('subject_id', $data['subject_id'])
            ->orderBy('sequence')->get(['name_ar', 'name_en', 'max_score', 'weight'])->toArray();
        $configured = AssessmentComponent::query()->where('term_id', $data['term_id'])
            ->where('grade_level_id', $data['grade_level_id'])->distinct()->pluck('subject_id');

        $term = Term::query()->findOrFail($data['term_id']);
        $grade = GradeLevel::query()->findOrFail($data['grade_level_id']);
        $count = 0;

        DB::transaction(function () use ($source, $configured, $term, $grade, $save, &$count) {
            foreach (Subject::query()->whereNotIn('id', $configured)->get() as $subject) {
                $save->handle($term, $grade, $subject, $source);
                $count++;
            }
        });

        return back()->with('success', __('Copied to :count subjects.', ['count' => $count]));
    }

    /** The default scale, or one stage's own scale (?stage_id=). */
    public function scale(Request $request): Response
    {
        $stage = Stage::query()->find($request->integer('stage_id'));
        $own = $stage ? GradingScale::query()->with('bands')->where('stage_id', $stage->id)->first() : null;
        $scale = $stage ? ($own ?? GradingScale::forSchool()) : GradingScale::forSchool();

        return Inertia::render('Settings/Grading', [
            'stages' => Stage::query()->orderBy('sequence')->get()->map(fn (Stage $s) => ['id' => $s->id, 'name' => $s->name]),
            'stageId' => $stage?->id,
            'usesDefault' => $stage !== null && $own === null,
            'scale' => $scale ? [
                'pass_percent' => $scale->pass_percent,
                'bands' => $scale->bands->map(fn ($b) => ['min_percent' => $b->min_percent, 'label_ar' => $b->label_ar, 'label_en' => $b->label_en])->values(),
            ] : null,
        ]);
    }

    public function saveScale(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'stage_id' => ['nullable', 'integer', ExistsInCurrentSchool::inTable('stages')],
            'use_default' => ['sometimes', 'boolean'],
            'pass_percent' => ['required_unless:use_default,true', 'numeric', 'between:0,100'],
            'bands' => ['required_unless:use_default,true', 'array', 'min:2', 'max:12'],
            'bands.*.min_percent' => ['required', 'numeric', 'between:0,100', 'distinct'],
            'bands.*.label_ar' => ['required', 'string', 'max:30'],
            'bands.*.label_en' => ['nullable', 'string', 'max:30'],
        ]);
        $stageId = $data['stage_id'] ?? null;

        // A stage can go back to the school default by dropping its own scale.
        if ($stageId !== null && $request->boolean('use_default')) {
            GradingScale::query()->where('stage_id', $stageId)->delete();

            return back()->with('success', __('Changes saved.'));
        }

        if (! collect($data['bands'])->contains(fn ($b) => (float) $b['min_percent'] === 0.0)) {
            throw ValidationException::withMessages(['bands' => __('grades.bands_invalid')]);
        }

        DB::transaction(function () use ($data, $stageId) {
            $scale = GradingScale::query()->where('stage_id', $stageId)->first()
                ?? GradingScale::query()->create(['stage_id' => $stageId, 'name' => 'السلم العام', 'is_default' => $stageId === null]);
            $scale->update(['pass_percent' => $data['pass_percent']]);
            $scale->bands()->delete();
            foreach ($data['bands'] as $band) {
                $scale->bands()->create($band);
            }
        });

        return back()->with('success', __('Changes saved.'));
    }

    private function currentTerms()
    {
        $year = AcademicYear::query()->where('is_current', true)->first();

        return $year ? $year->terms()->get() : collect();
    }

    private function grades()
    {
        return GradeLevel::query()
            ->join('stages', 'stages.id', '=', 'grade_levels.stage_id')
            ->orderBy('stages.sequence')->orderBy('grade_levels.sequence')
            ->get(['grade_levels.*']);
    }
}
