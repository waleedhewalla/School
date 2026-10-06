<?php

namespace App\Http\Controllers\Web;

use App\Actions\Timetable\AssignTeacher;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StaffMemberRequest;
use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\Section;
use App\Models\StaffMember;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\TimetableEntry;
use App\Rules\ExistsInCurrentSchool;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Staff records and who teaches what in each section. */
class StaffController extends Controller
{
    public function index(Request $request, CurrentSchool $currentSchool): Response
    {
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Staff/Index', [
            'staff' => StaffMember::query()->with('user:id,email')->withCount('teachingAssignments')
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                    ->where('name_ar', 'like', "%{$search}%")->orWhere('name_en', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")))
                ->orderBy('name_ar')->get()
                ->map(fn (StaffMember $s) => [
                    ...$s->only(['id', 'employee_number', 'name_ar', 'name_en', 'national_id', 'job_title', 'email', 'campus_id', 'user_id', 'status', 'teaching_assignments_count']),
                    'phone' => $s->getRawOriginal('phone'),
                    'login' => $s->user?->email,
                ]),
            // School members not yet linked to a staff record.
            'users' => $currentSchool->get()->users()->wherePivot('status', 'active')
                ->whereNotIn('users.id', StaffMember::query()->whereNotNull('user_id')->pluck('user_id'))
                ->orderBy('name')->get(['users.id', 'users.name', 'users.email'])
                ->map(fn ($u) => ['id' => $u->id, 'label' => $u->name.' — '.$u->email]),
            'campuses' => Campus::query()->get()->map(fn (Campus $c) => ['id' => $c->id, 'name' => $c->name]),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(StaffMemberRequest $request): RedirectResponse
    {
        StaffMember::query()->create($request->validated());

        return back()->with('success', __('Changes saved.'));
    }

    public function update(StaffMemberRequest $request, StaffMember $staffMember): RedirectResponse
    {
        $staffMember->update($request->validated());

        return back()->with('success', __('Changes saved.'));
    }

    public function assignments(Request $request): Response
    {
        $year = AcademicYear::query()->where('is_current', true)->first();
        $sections = Section::query()->with('gradeLevel')
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->orderBy('grade_level_id')->orderBy('name')->get();
        $sectionId = $request->integer('section') ?: $sections->first()?->id;

        return Inertia::render('Staff/Assignments', [
            'sections' => $sections->map(fn (Section $s) => ['id' => $s->id, 'label' => $s->gradeLevel->name.' / '.$s->name]),
            'sectionId' => $sectionId,
            'subjects' => Subject::query()->ordered()->get()->map(fn (Subject $s) => ['id' => $s->id, 'name' => $s->name]),
            'teachers' => StaffMember::query()->where('status', 'active')->orderBy('name_ar')->get()
                ->map(fn (StaffMember $s) => ['id' => $s->id, 'name' => $s->name]),
            'assignments' => TeachingAssignment::query()->where('section_id', $sectionId)->get()
                ->map(fn (TeachingAssignment $a) => $a->only(['id', 'subject_id', 'staff_member_id', 'is_homeroom'])),
            'load' => TeachingAssignment::query()->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
                ->selectRaw('staff_member_id, count(*) as total')->groupBy('staff_member_id')->pluck('total', 'staff_member_id'),
        ]);
    }

    public function assign(Request $request, Section $section, AssignTeacher $assign): RedirectResponse
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer', ExistsInCurrentSchool::in('subjects')],
            'staff_member_id' => ['nullable', 'integer', ExistsInCurrentSchool::in('staff_members')],
            'is_homeroom' => ['boolean'],
        ]);

        if (empty($data['staff_member_id'])) {
            DB::transaction(function () use ($section, $data) {
                $assignment = TeachingAssignment::query()->where('section_id', $section->id)->where('subject_id', $data['subject_id'])->first();
                if ($assignment) {
                    TimetableEntry::query()->where('teaching_assignment_id', $assignment->id)->delete();
                    $assignment->delete();
                }
            });
        } else {
            $assign->handle($section, (int) $data['subject_id'], (int) $data['staff_member_id'], (bool) ($data['is_homeroom'] ?? false));
        }

        return back()->with('success', __('Changes saved.'));
    }
}
