<?php

namespace App\Http\Controllers\Web;

use App\Actions\Timetable\PlaceLesson;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Period;
use App\Models\Section;
use App\Models\TeachingAssignment;
use App\Models\TimetableEntry;
use App\Rules\ExistsInCurrentSchool;
use App\Support\Tenancy\CurrentSchool;
use App\Support\TimetableGrid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimetableController extends Controller
{
    /** Section timetables: view for staff, edit for timetable managers. */
    public function index(Request $request, CurrentSchool $currentSchool): Response
    {
        $year = AcademicYear::query()->where('is_current', true)->first();
        $sections = Section::query()->with('gradeLevel')
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->orderBy('grade_level_id')->orderBy('name')->get();
        $section = $sections->firstWhere('id', $request->integer('section_id'));

        return Inertia::render('Timetable/Section', [
            'sections' => $sections->map(fn (Section $s) => ['id' => $s->id, 'label' => $s->gradeLevel->name.' / '.$s->name]),
            'sectionId' => $section?->id,
            'grid' => $section ? TimetableGrid::build(TimetableEntry::query()->where('section_id', $section->id), $currentSchool->get()->schoolDays()) : null,
            'assignments' => $section ? TeachingAssignment::query()->with('subject', 'staffMember')->where('section_id', $section->id)->get()
                ->map(fn (TeachingAssignment $a) => ['id' => $a->id, 'label' => $a->subject->name.' — '.$a->staffMember->name]) : [],
            'canEdit' => $request->user()->can(Permission::TimetableManage),
        ]);
    }

    public function place(Request $request, Section $section, PlaceLesson $place): RedirectResponse
    {
        $data = $request->validate([
            'day' => ['required', 'integer', 'between:0,6'],
            'period_id' => ['required', 'integer', ExistsInCurrentSchool::inTable('periods')],
            'teaching_assignment_id' => ['nullable', 'integer', ExistsInCurrentSchool::inTable('teaching_assignments')],
            'room' => ['nullable', 'string', 'max:30'],
        ]);

        if (empty($data['teaching_assignment_id'])) {
            TimetableEntry::query()->where('section_id', $section->id)
                ->where('day', $data['day'])->where('period_id', $data['period_id'])->delete();
        } else {
            $place->handle(
                $section,
                (int) $data['day'],
                Period::query()->findOrFail($data['period_id']),
                TeachingAssignment::query()->findOrFail($data['teaching_assignment_id']),
                $data['room'] ?? null,
            );
        }

        return back();
    }

    /** A teacher's own week. */
    public function mine(Request $request, CurrentSchool $currentSchool): Response
    {
        return Inertia::render('Timetable/Mine', [
            'grid' => TimetableGrid::build(
                TimetableEntry::query()->whereHas('staffMember', fn ($q) => $q->where('user_id', $request->user()->id)),
                $currentSchool->get()->schoolDays(),
                withSection: true,
            ),
        ]);
    }
}
