<?php

namespace App\Http\Controllers\Web;

use App\Actions\Attendance\RecordAttendance;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AttendanceCodeResource;
use App\Models\AcademicYear;
use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use App\Models\Period;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /** Pick a section and date; shows the register when both are chosen. */
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'section_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'period' => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);

        $date = Carbon::parse($data['date'] ?? now()->toDateString());
        $period = (int) ($data['period'] ?? AttendanceRecord::DAILY);
        $sections = $this->sectionsFor($request);
        $section = isset($data['section_id']) ? $sections->firstWhere('id', (int) $data['section_id']) : null;

        return Inertia::render('Attendance/Register', [
            'sections' => $sections->map(fn (Section $s) => ['id' => $s->id, 'label' => $s->gradeLevel->name.' / '.$s->name])->values(),
            'periods' => Period::query()->lessons()->get()->map(fn (Period $p) => ['sequence' => $p->sequence, 'name' => $p->name]),
            'codes' => AttendanceCodeResource::collection(AttendanceCode::query()->orderBy('sequence')->get()),
            'filters' => ['section_id' => $section?->id, 'date' => $date->toDateString(), 'period' => $period],
            'register' => $section ? $this->register($section, $date, $period) : null,
            'canEdit' => $section ? $request->user()->can('recordAttendance', $section) : false,
        ]);
    }

    public function store(Request $request, Section $section, RecordAttendance $record): RedirectResponse
    {
        Gate::authorize('recordAttendance', $section);

        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'period' => ['required', 'integer', 'min:0', 'max:20'],
            'records' => ['required', 'array', 'min:1', 'max:200'],
            'records.*.student_id' => ['required', 'integer', 'distinct'],
            'records.*.code' => ['required', 'string', 'max:5'],
            'records.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $record->handle($section, Carbon::parse($data['date']), (int) $data['period'], $data['records'], $request->user());

        return back()->with('success', __('Attendance saved.'));
    }

    /** Sections the user may see registers for: all of them, or the ones they teach. */
    private function sectionsFor(Request $request)
    {
        $year = AcademicYear::query()->where('is_current', true)->first();
        $user = $request->user();

        return Section::query()
            ->with('gradeLevel')
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->when(
                ! $user->can(Permission::AttendanceView) && ! $user->can(Permission::AttendanceManage),
                fn ($q) => $q->whereHas('teachingAssignments.staffMember', fn ($s) => $s->where('user_id', $user->id)),
            )
            ->orderBy('grade_level_id')->orderBy('name')
            ->get();
    }

    /** @return array<string, mixed> */
    private function register(Section $section, Carbon $date, int $period): array
    {
        $records = AttendanceRecord::query()
            ->with('code')
            ->where('section_id', $section->id)
            ->where('date', $date->toDateString())
            ->where('period', $period)
            ->get()
            ->keyBy('student_id');

        return [
            'taken' => $records->isNotEmpty(),
            'students' => Student::query()->inSection($section->id)
                ->orderBy('family_name_ar')->orderBy('first_name_ar')->get()
                ->map(fn (Student $s) => [
                    'student_id' => $s->id,
                    'student_number' => $s->student_number,
                    'name' => $s->name,
                    'code' => $records->get($s->id)?->code->code,
                    'note' => $records->get($s->id)?->note,
                ])->values(),
        ];
    }
}
