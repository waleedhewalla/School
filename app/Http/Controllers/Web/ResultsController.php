<?php

namespace App\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ReportCardComment;
use App\Models\Section;
use App\Models\Student;
use App\Models\Term;
use App\Support\AttendanceSummary;
use App\Support\Dates\SchoolDate;
use App\Support\Grades\TermResults;
use App\Support\Pdf\InlineAssets;
use App\Support\Pdf\PdfRenderer;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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
            'comments' => $term && $section ? ReportCardComment::query()->where('term_id', $term->id)->pluck('comment', 'student_id') : [],
            'canManage' => $request->user()->can(Permission::GradesManage),
            'canComment' => $section ? self::canComment($request->user(), $section) : false,
            'pdfEnabled' => config('services.pdf.driver') === 'gotenberg',
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

    /** The class teacher's remark for a student's report card (empty removes it). */
    public function comment(Request $request, Section $section, Term $term): RedirectResponse
    {
        abort_unless((int) $term->academic_year_id === (int) $section->academic_year_id, 404);
        abort_unless(self::canComment($request->user(), $section), 403);

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);
        abort_unless(Student::query()->inSection($section->id)->whereKey($data['student_id'])->exists(), 404);

        if (blank($data['comment'])) {
            ReportCardComment::query()->where('term_id', $term->id)->where('student_id', $data['student_id'])->delete();
        } else {
            ReportCardComment::query()->updateOrCreate(
                ['term_id' => $term->id, 'student_id' => $data['student_id']],
                ['comment' => trim($data['comment']), 'author_id' => $request->user()->id],
            );
        }

        return back()->with('success', __('Changes saved.'));
    }

    /** Grade managers, or the section's class (homeroom) teacher. */
    public static function canComment($user, Section $section): bool
    {
        return $user->can(Permission::GradesManage)
            || ($user->can(Permission::GradesRecord) && $section->teachingAssignments()->where('is_homeroom', true)
                ->whereHas('staffMember', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))->exists());
    }

    /** Server-rendered PDF of the section's report cards (when a PDF service is configured). */
    public function pdf(Request $request, Section $section, Term $term, CurrentSchool $currentSchool, PdfRenderer $pdf): HttpResponse
    {
        abort_unless(config('services.pdf.driver') === 'gotenberg', 404);
        abort_unless($request->user()->can(Permission::GradesView), 403);

        $html = $this->print($request, $section, $term, $currentSchool)->with('inlineCss', InlineAssets::css())->render();
        $name = 'report-cards-'.Str::slug($section->gradeLevel->name_en ?: 'section').'-'.$section->id.'-term-'.$term->sequence.'.pdf';

        return response($pdf->render($html), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
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
            'attendance' => AttendanceSummary::forStudents($students->pluck('id'), $term->starts_on, $term->ends_on),
            'comments' => ReportCardComment::query()->where('term_id', $term->id)->whereIn('student_id', $students->pluck('id'))->pluck('comment', 'student_id'),
            'issued' => SchoolDate::display(now(), $currentSchool->get()->date_display),
            'inlineCss' => null,
        ]);
    }
}
