<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AdmissionWindow;
use App\Models\GradeLevel;
use App\Rules\ExistsInCurrentSchool;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Intake windows: per grade and year, the dates, seats and birth-date range. */
class AdmissionWindowController extends Controller
{
    public function index(CurrentSchool $currentSchool): Response
    {
        return Inertia::render('Admissions/Windows', [
            'windows' => AdmissionWindow::query()->with('gradeLevel', 'academicYear')
                ->withCount('applications')
                ->orderByDesc('academic_year_id')->orderBy('grade_level_id')->get()
                ->map(fn (AdmissionWindow $w) => [
                    ...$w->only(['id', 'academic_year_id', 'grade_level_id', 'seats', 'exception_days', 'applications_count']),
                    'opens_on' => $w->opens_on->toDateString(),
                    'closes_on' => $w->closes_on->toDateString(),
                    'born_from' => $w->born_from?->toDateString(),
                    'born_to' => $w->born_to?->toDateString(),
                    'grade' => $w->gradeLevel->name,
                    'year' => $w->academicYear->name,
                    'seats_left' => $w->seatsLeft(),
                    'open' => $w->opens_on->lte(today()) && $w->closes_on->gte(today()),
                ]),
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name']),
            'grades' => GradeLevel::query()->orderBy('stage_id')->orderBy('sequence')->get()->map(fn (GradeLevel $g) => ['id' => $g->id, 'name' => $g->name]),
            'publicUrl' => route('apply.create', $currentSchool->get()->slug),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AdmissionWindow::query()->create($this->validated($request));

        return back()->with('success', __('admissions.window_saved'));
    }

    public function update(Request $request, AdmissionWindow $window): RedirectResponse
    {
        $window->update($this->validated($request, $window));

        return back()->with('success', __('admissions.window_saved'));
    }

    public function destroy(AdmissionWindow $window): RedirectResponse
    {
        if ($window->applications()->exists()) {
            return back()->withErrors(['window' => __('admissions.window_has_applications')]);
        }
        $window->delete();

        return back()->with('success', __('admissions.window_deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?AdmissionWindow $window = null): array
    {
        return $request->validate([
            'academic_year_id' => ['required', 'integer', ExistsInCurrentSchool::in('academic_years')],
            'grade_level_id' => ['required', 'integer', ExistsInCurrentSchool::inTable('grade_levels'),
                Rule::unique('admission_windows')->where('academic_year_id', $request->integer('academic_year_id'))->ignore($window?->id)],
            'seats' => ['required', 'integer', 'min:1', 'max:2000'],
            'opens_on' => ['required', 'date'],
            'closes_on' => ['required', 'date', 'after_or_equal:opens_on'],
            'born_from' => ['nullable', 'date'],
            'born_to' => ['nullable', 'date', 'after_or_equal:born_from'],
            'exception_days' => ['integer', 'min:0', 'max:365'],
        ]);
    }
}
