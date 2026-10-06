<?php

namespace App\Http\Controllers\Web;

use App\Actions\Students\PromoteStudents;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Rules\ExistsInCurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Year-end promotion, one section at a time. */
class PromotionController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'from' => ['nullable', 'integer'],
            'to' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
        ]);

        $years = AcademicYear::query()->orderBy('starts_on')->get(['id', 'name', 'is_current']);
        $from = $years->firstWhere('id', (int) ($filters['from'] ?? 0)) ?? $years->firstWhere('is_current', true);
        $to = $years->firstWhere('id', (int) ($filters['to'] ?? 0)) ?? $years->first(fn ($y) => $from && $y->starts_on > $from->starts_on);

        $sections = $from ? Section::query()->with('gradeLevel')->where('academic_year_id', $from->id)
            ->orderBy('grade_level_id')->orderBy('name')->get() : collect();
        $section = $sections->firstWhere('id', (int) ($filters['section_id'] ?? 0));

        return Inertia::render('Promotions/Index', [
            'years' => $years->map->only(['id', 'name']),
            'filters' => ['from' => $from?->id, 'to' => $to?->id, 'section_id' => $section?->id],
            'sections' => $sections->map(fn (Section $s) => ['id' => $s->id, 'label' => $s->gradeLevel->name.' / '.$s->name]),
            'students' => $section ? Enrollment::query()->with('student')
                ->where('section_id', $section->id)->where('status', EnrollmentStatus::Active)->get()
                ->sortBy(fn (Enrollment $e) => $e->student->name_ar)->values()
                ->map(fn (Enrollment $e) => ['student_id' => $e->student_id, 'name' => $e->student->name]) : [],
            'targetSections' => $section && $to ? $this->targets($section, $to) : [],
        ]);
    }

    public function store(Request $request, PromoteStudents $promote): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'integer', ExistsInCurrentSchool::in('academic_years')],
            'to' => ['required', 'integer', 'different:from', ExistsInCurrentSchool::in('academic_years')],
            'decisions' => ['required', 'array', 'min:1', 'max:200'],
            'decisions.*.student_id' => ['required', 'integer', 'distinct'],
            'decisions.*.outcome' => ['required', Rule::in(['promoted', 'repeated', 'graduated'])],
            'decisions.*.section_id' => ['nullable', 'integer', ExistsInCurrentSchool::in('sections')],
        ]);

        $promote->handle(
            AcademicYear::query()->findOrFail($data['from']),
            AcademicYear::query()->findOrFail($data['to']),
            $data['decisions'],
        );

        return back()->with('success', __('Promotion saved for :count students.', ['count' => count($data['decisions'])]));
    }

    /** Sections in the target year for the same grade (repeat) and the next grade (promote). */
    private function targets(Section $section, AcademicYear $to): array
    {
        $order = GradeLevel::query()
            ->join('stages', 'stages.id', '=', 'grade_levels.stage_id')
            ->orderBy('stages.sequence')->orderBy('grade_levels.sequence')
            ->pluck('grade_levels.id')->values();
        $next = $order[$order->search($section->grade_level_id) + 1] ?? null;

        $list = fn (?int $gradeId) => $gradeId === null ? [] : Section::query()
            ->where('academic_year_id', $to->id)->where('grade_level_id', $gradeId)
            ->orderBy('name')->get(['id', 'name'])->all();

        return ['promoted' => $list($next), 'repeated' => $list($section->grade_level_id), 'has_next_grade' => $next !== null];
    }
}
