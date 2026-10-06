<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\StudentResource;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Student;
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

    public function show(Student $student): Response
    {
        Gate::authorize('view', $student);

        $student->load('guardians', 'enrollments.gradeLevel', 'enrollments.section', 'enrollments.academicYear');

        return Inertia::render('Students/Show', [
            'student' => new StudentResource($student),
            'years' => $student->enrollments->mapWithKeys(fn ($e) => [$e->academic_year_id => $e->academicYear->name]),
            'attendance' => $student->attendanceRecords()->with('code')
                ->latest('date')->limit(30)->get()
                ->map(fn ($r) => ['date' => $r->date->toDateString(), 'period' => $r->period, 'code' => $r->code->code, 'kind' => $r->code->kind, 'name' => $r->code->name, 'note' => $r->note]),
        ]);
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
