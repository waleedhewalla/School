<?php

namespace App\Http\Controllers\Web;

use App\Actions\Students\AdmitStudent;
use App\Enums\EnrollmentStatus;
use App\Enums\GuardianRelationship;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StudentRequest;
use App\Http\Resources\V1\StudentResource;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'section_id' => ['nullable', 'integer'],
        ]);

        $students = Student::query()
            ->with('currentEnrollment.gradeLevel', 'currentEnrollment.section')
            ->search($filters['search'] ?? null)
            ->when($filters['section_id'] ?? null, fn ($q, $id) => $q->inSection((int) $id))
            ->orderBy('family_name_ar')->orderBy('first_name_ar')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Students/Index', [
            'students' => StudentResource::collection($students),
            'sections' => $this->currentSections(),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, Student $student): Response
    {
        Gate::authorize('view', $student);

        $student->load('guardians', 'enrollments.gradeLevel', 'enrollments.section', 'enrollments.academicYear');
        $active = $student->enrollments->firstWhere('status', EnrollmentStatus::Active);

        return Inertia::render('Students/Show', [
            'canManage' => $request->user()->can(Permission::StudentsManage),
            'otherSections' => $active ? Section::query()
                ->where('academic_year_id', $active->academic_year_id)
                ->where('grade_level_id', $active->grade_level_id)
                ->whereKeyNot($active->section_id ?? 0)
                ->orderBy('name')->get(['id', 'name']) : [],
            'student' => new StudentResource($student),
            'years' => $student->enrollments->mapWithKeys(fn ($e) => [$e->academic_year_id => $e->academicYear->name]),
            'attendance' => $student->attendanceRecords()->with('code')
                ->latest('date')->limit(30)->get()
                ->map(fn ($r) => ['date' => $r->date->toDateString(), 'period' => $r->period, 'code' => $r->code->code, 'kind' => $r->code->kind, 'name' => $r->code->name, 'note' => $r->note]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Students/Form', ['student' => null] + $this->formOptions());
    }

    public function store(StudentRequest $request, AdmitStudent $admit): RedirectResponse
    {
        $student = $admit->handle($request->validated());

        return redirect()->route('students.show', $student)->with('success', __('Student admitted.'));
    }

    public function edit(Student $student): Response
    {
        return Inertia::render('Students/Form', ['student' => new StudentResource($student)] + $this->formOptions());
    }

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        $student->update($request->validated());

        return redirect()->route('students.show', $student)->with('success', __('Changes saved.'));
    }

    /** Choices for the admission form: current year, grades and that year's sections. */
    private function formOptions(): array
    {
        $year = AcademicYear::query()->where('is_current', true)->first();

        return [
            'year' => $year?->only(['id', 'name']),
            'grades' => GradeLevel::query()
                ->join('stages', 'stages.id', '=', 'grade_levels.stage_id')
                ->orderBy('stages.sequence')->orderBy('grade_levels.sequence')
                ->get(['grade_levels.*'])
                ->map(fn (GradeLevel $g) => ['id' => $g->id, 'name' => $g->name]),
            'sections' => $year ? Section::query()->where('academic_year_id', $year->id)->orderBy('name')
                ->get(['id', 'grade_level_id', 'name', 'capacity']) : [],
            'relationships' => collect(GuardianRelationship::cases())->map->value,
        ];
    }

    /** @return list<array{id: int, label: string}> */
    private function currentSections(): array
    {
        $year = AcademicYear::query()->where('is_current', true)->first();

        return Section::query()
            ->with('gradeLevel')
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->orderBy('grade_level_id')->orderBy('name')
            ->get()
            ->map(fn (Section $s) => ['id' => $s->id, 'label' => $s->gradeLevel->name.' / '.$s->name])
            ->all();
    }
}
