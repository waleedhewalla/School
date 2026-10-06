<?php

namespace App\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\Term;
use App\Support\Dates\SchoolDate;
use App\Support\Grades\TermResults;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ResultsController extends Controller
{
    public function index(Request $request): Response
    {
        $year = AcademicYear::query()->where('is_current', true)->first();
        $terms = $year ? $year->terms()->get() : collect();
        $term = $terms->firstWhere('id', $request->integer('term_id')) ?? $terms->first();
        $sections = Section::query()->with('gradeLevel')
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->orderBy('grade_level_id')->orderBy('name')->get();
        $section = $sections->firstWhere('id', $request->integer('section_id'));

        return Inertia::render('Results/Section', [
            'terms' => $terms->map(fn (Term $t) => ['id' => $t->id, 'name' => $t->name, 'marks_open' => $t->marks_open, 'published' => $t->resultsPublished()]),
            'sections' => $sections->map(fn (Section $s) => ['id' => $s->id, 'label' => $s->gradeLevel->name.' / '.$s->name]),
            'filters' => ['term_id' => $term?->id, 'section_id' => $section?->id],
            'results' => $term && $section ? TermResults::forSection($section, $term) : null,
            'canManage' => $request->user()->can(Permission::GradesManage),
        ]);
    }

    /** Open/close mark entry and publish/unpublish results for a term. */
    public function updateTerm(Request $request, Term $term): RedirectResponse
    {
        $data = $request->validate([
            'marks_open' => ['sometimes', 'boolean'],
            'published' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('marks_open', $data)) {
            $term->marks_open = $data['marks_open'];
        }
        if (array_key_exists('published', $data)) {
            $term->results_published_at = $data['published'] ? now() : null;
        }
        $term->save();

        return back()->with('success', __('Changes saved.'));
    }

    /** Printable report cards: a whole section, or one student (?student_id=). */
    public function print(Request $request, Section $section, Term $term, CurrentSchool $currentSchool): View
    {
        abort_unless((int) $term->academic_year_id === (int) $section->academic_year_id, 404);

        $students = Student::query()->inSection($section->id)
            ->when($request->integer('student_id'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('family_name_ar')->orderBy('first_name_ar')->get();

        $isStaff = $request->user()->can(Permission::GradesView) || $request->user()->can(Permission::GradesManage);
        if (! $isStaff) {
            // Guardians: only their own child, only once results are published.
            abort_unless($term->resultsPublished() && $students->count() === 1, 403);
            Gate::authorize('view', $students->first());
        }

        $section->load('gradeLevel', 'academicYear');

        return view('report-cards.print', [
            'school' => $currentSchool->get(),
            'section' => $section,
            'term' => $term,
            'results' => TermResults::forSection($section, $term, $students),
            'issued' => SchoolDate::display(now(), $currentSchool->get()->date_display),
        ]);
    }
}
