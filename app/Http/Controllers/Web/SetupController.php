<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AcademicYearRequest;
use App\Http\Requests\Api\V1\SectionRequest;
use App\Http\Requests\Api\V1\SubjectRequest;
use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** School structure screens: academic years and terms, sections, subjects. */
class SetupController extends Controller
{
    public function years(): Response
    {
        return Inertia::render('Setup/Years', [
            'years' => AcademicYear::query()->with(['terms' => fn ($q) => $q->orderBy('sequence')])->withCount('sections')
                ->orderByDesc('starts_on')->get()
                ->map(fn (AcademicYear $y) => [
                    'id' => $y->id, 'name' => $y->name, 'is_current' => $y->is_current, 'sections_count' => $y->sections_count,
                    'starts_on' => $y->starts_on->toDateString(), 'ends_on' => $y->ends_on->toDateString(),
                    'terms' => $y->terms->map(fn (Term $t) => [
                        'id' => $t->id, 'name_ar' => $t->name_ar, 'name_en' => $t->name_en, 'sequence' => $t->sequence,
                        'starts_on' => $t->starts_on->toDateString(), 'ends_on' => $t->ends_on->toDateString(),
                    ]),
                ]),
        ]);
    }

    public function storeYear(AcademicYearRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $year = AcademicYear::query()->create($request->safe()->except(['terms', 'is_current']));
            foreach ($request->validated('terms', []) as $index => $term) {
                $year->terms()->create($term + ['sequence' => $index + 1]);
            }
            if ($request->boolean('is_current') || AcademicYear::query()->count() === 1) {
                $year->makeCurrent();
            }
        });

        return back()->with('success', __('Changes saved.'));
    }

    public function updateYear(AcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->update($request->safe()->except(['terms', 'is_current']));
        if ($request->boolean('is_current')) {
            $academicYear->makeCurrent();
        }

        return back()->with('success', __('Changes saved.'));
    }

    public function destroyYear(AcademicYear $academicYear): RedirectResponse
    {
        if ($academicYear->is_current || $academicYear->sections()->exists()) {
            return back()->withErrors(['year' => __('setup.year_in_use')]);
        }
        $academicYear->terms()->delete();
        $academicYear->delete();

        return back()->with('success', __('Deleted.'));
    }

    public function storeTerm(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $data = $this->termRules($request, $academicYear);
        $academicYear->terms()->create($data + ['sequence' => $academicYear->terms()->max('sequence') + 1]);

        return back()->with('success', __('Changes saved.'));
    }

    public function updateTerm(Request $request, Term $term): RedirectResponse
    {
        $term->update($this->termRules($request, $term->academicYear));

        return back()->with('success', __('Changes saved.'));
    }

    public function destroyTerm(Term $term): RedirectResponse
    {
        if ($term->assessmentComponents()->exists()) {
            return back()->withErrors(['term' => __('setup.term_in_use')]);
        }
        $term->delete();

        return back()->with('success', __('Deleted.'));
    }

    public function sections(Request $request): Response
    {
        $years = AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name', 'is_current']);
        $yearId = $request->integer('year') ?: $years->firstWhere('is_current', true)?->id ?? $years->first()?->id;

        return Inertia::render('Setup/Sections', [
            'years' => $years,
            'yearId' => $yearId,
            'grades' => GradeLevel::query()->with('stage')->orderBy('stage_id')->orderBy('sequence')->get()
                ->map(fn (GradeLevel $g) => ['id' => $g->id, 'name' => $g->name, 'stage' => $g->stage->name]),
            'sections' => Section::query()->where('academic_year_id', $yearId)->withCount(['enrollments' => fn ($q) => $q->where('status', 'active')])
                ->orderBy('name')->get()
                ->map(fn (Section $s) => $s->only(['id', 'grade_level_id', 'campus_id', 'name', 'capacity', 'enrollments_count'])),
            'campuses' => Campus::query()->get()->map(fn (Campus $c) => ['id' => $c->id, 'name' => $c->name]),
        ]);
    }

    public function storeSection(SectionRequest $request): RedirectResponse
    {
        Section::query()->create($request->validated());

        return back()->with('success', __('Changes saved.'));
    }

    public function updateSection(SectionRequest $request, Section $section): RedirectResponse
    {
        $section->update($request->safe()->except(['academic_year_id', 'grade_level_id']));

        return back()->with('success', __('Changes saved.'));
    }

    public function destroySection(Section $section): RedirectResponse
    {
        if ($section->enrollments()->exists()) {
            return back()->withErrors(['section' => __('setup.section_in_use')]);
        }
        $section->delete();

        return back()->with('success', __('Deleted.'));
    }

    public function subjects(): Response
    {
        return Inertia::render('Setup/Subjects', [
            'subjects' => Subject::query()->withCount('teachingAssignments')->orderBy('sequence')->orderBy('id')->get()
                ->map(fn (Subject $s) => $s->only(['id', 'code', 'name_ar', 'name_en', 'sequence', 'teaching_assignments_count'])),
        ]);
    }

    public function storeSubject(SubjectRequest $request): RedirectResponse
    {
        Subject::query()->create($request->validated() + ['sequence' => Subject::query()->max('sequence') + 1]);

        return back()->with('success', __('Changes saved.'));
    }

    public function updateSubject(SubjectRequest $request, Subject $subject): RedirectResponse
    {
        $subject->update($request->validated());

        return back()->with('success', __('Changes saved.'));
    }

    public function destroySubject(Subject $subject): RedirectResponse
    {
        if ($subject->teachingAssignments()->exists()) {
            return back()->withErrors(['subject' => __('setup.subject_in_use')]);
        }
        $subject->delete();

        return back()->with('success', __('Deleted.'));
    }

    /** @return array<string, mixed> */
    private function termRules(Request $request, AcademicYear $year): array
    {
        return $request->validate([
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'starts_on' => ['required', 'date', 'after_or_equal:'.$year->starts_on->toDateString()],
            'ends_on' => ['required', 'date', 'after:starts_on', 'before_or_equal:'.$year->ends_on->toDateString()],
        ]);
    }
}
