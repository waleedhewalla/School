<?php

namespace App\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\Term;
use App\Rules\ExistsInCurrentSchool;
use App\Support\AttendanceSummary;
use App\Support\Dates\SchoolDate;
use App\Support\Export\Spreadsheet;
use App\Support\Grades\TermResults;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Excel exports for the Ministry's Noor system and for the school's own
 * records. The student sheet uses the same Arabic headers as the Noor
 * import, so it can be uploaded back. Every export is audited.
 */
class ExportController extends Controller
{
    public function index(Request $request): Response
    {
        $year = $this->year($request);

        return Inertia::render('Exports/Index', [
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'name', 'is_current']),
            'yearId' => $year?->id,
            'sections' => $year ? Section::query()->with('gradeLevel')->where('academic_year_id', $year->id)
                ->orderBy('grade_level_id')->orderBy('name')->get()
                ->map(fn (Section $s) => ['id' => $s->id, 'label' => $s->gradeLevel->name.' / '.$s->name]) : [],
            'terms' => $year ? $year->terms()->orderBy('sequence')->get()
                ->map(fn (Term $t) => ['id' => $t->id, 'name' => $t->name, 'starts_on' => $t->starts_on->toDateString(), 'ends_on' => $t->ends_on->toDateString()]) : [],
            'allowed' => [
                'students' => $request->user()->can(Permission::StudentsManage),
                'attendance' => $request->user()->can(Permission::AttendanceView),
                'results' => $request->user()->can(Permission::GradesView),
            ],
        ]);
    }

    public function students(Request $request): BinaryFileResponse
    {
        Gate::authorize(Permission::StudentsManage);
        $year = $this->year($request);
        abort_if($year === null, 404);
        $sectionId = $request->validate(['section_id' => ['nullable', 'integer', ExistsInCurrentSchool::in('sections')]])['section_id'] ?? null;

        $rows = $this->enrollments($year, $sectionId)->map(function (Enrollment $e) {
            $s = $e->student;
            /** @var Guardian|null $g */
            $g = $s->guardians->sortByDesc(fn ($g) => $g->pivot->is_primary)->first();
            $hijri = $s->date_of_birth ? SchoolDate::hijriParts($s->date_of_birth) : null;

            return [
                $s->national_id, $s->name_ar, $s->name_en,
                $s->gender->value === 'male' ? 'ذكر' : 'أنثى',
                $hijri ? sprintf('%04d/%02d/%02d', $hijri['year'], $hijri['month'], $hijri['day']) : null,
                $s->nationality === 'SA' ? 'سعودي' : $s->nationality,
                $e->gradeLevel->name_ar, $e->section?->name,
                $g?->name_ar, $g?->national_id, $g?->phone,
                $s->student_number, $s->date_of_birth?->toDateString(),
            ];
        });

        $this->audit($request, 'students', $year);

        return Spreadsheet::download("students-{$year->name}.xlsx", [
            __('Students') => [[
                'رقم الهوية', 'اسم الطالب', 'اسم الطالب بالانجليزي', 'الجنس', 'تاريخ الميلاد (هـ)', 'الجنسية',
                'الصف', 'الفصل', 'اسم ولي الأمر', 'هوية ولي الأمر', 'جوال ولي الأمر', 'الرقم الدراسي', 'تاريخ الميلاد (م)',
            ], $rows],
        ]);
    }

    public function attendance(Request $request): BinaryFileResponse
    {
        Gate::authorize(Permission::AttendanceView);
        $year = $this->year($request);
        abort_if($year === null, 404);
        $data = $request->validate([
            'section_id' => ['nullable', 'integer', ExistsInCurrentSchool::in('sections')],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $enrollments = $this->enrollments($year, $data['section_id'] ?? null);
        $summary = AttendanceSummary::forStudents($enrollments->pluck('student_id'), CarbonImmutable::parse($data['from']), CarbonImmutable::parse($data['to']));

        $rows = $enrollments->map(function (Enrollment $e) use ($summary) {
            $s = $summary[$e->student_id];

            return [
                $e->student->student_number, $e->student->national_id, $e->student->name_ar,
                $e->gradeLevel->name_ar, $e->section?->name,
                $s['present'], $s['absent'], $s['late'], $s['excused'], AttendanceSummary::rate($s),
            ];
        });

        $this->audit($request, 'attendance', $year);

        return Spreadsheet::download("attendance-{$data['from']}-{$data['to']}.xlsx", [
            __('Attendance') => [['الرقم الدراسي', 'رقم الهوية', 'اسم الطالب', 'الصف', 'الفصل', 'حاضر', 'غائب', 'متأخر', 'غائب بعذر', 'نسبة الحضور %'], $rows],
        ]);
    }

    public function results(Request $request): BinaryFileResponse
    {
        Gate::authorize(Permission::GradesView);
        $data = $request->validate([
            'term_id' => ['required', 'integer', ExistsInCurrentSchool::inTable('terms')],
            'section_id' => ['nullable', 'integer', ExistsInCurrentSchool::in('sections')],
        ]);
        $term = Term::query()->findOrFail($data['term_id']);
        $sections = Section::query()->with('gradeLevel')->where('academic_year_id', $term->academic_year_id)
            ->when($data['section_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->orderBy('grade_level_id')->orderBy('name')->get();

        $sheets = [];
        foreach ($sections as $section) {
            $results = TermResults::forSection($section, $term);
            if ($results['students'] === []) {
                continue;
            }
            $headers = ['الرقم الدراسي', 'اسم الطالب'];
            foreach ($results['subjects'] as $subject) {
                array_push($headers, $subject['name'].' %', $subject['name'].' — التقدير');
            }
            array_push($headers, 'المعدل %', 'التقدير العام');

            $rows = array_map(function (array $student) use ($results) {
                $row = [$student['student_number'], $student['name']];
                foreach ($results['subjects'] as $subject) {
                    $r = $student['subjects'][$subject['id']];
                    array_push($row, $r['percent'], $r['grade']);
                }
                array_push($row, $student['average'], $student['average_grade']);

                return $row;
            }, $results['students']);

            $sheets[$section->gradeLevel->name_ar.' '.$section->name] = [$headers, $rows];
        }
        abort_if($sheets === [], 404, __('No results to export.'));

        $this->audit($request, 'results', $term->academicYear);

        return Spreadsheet::download("results-{$term->academicYear->name}-{$term->sequence}.xlsx", $sheets);
    }

    private function year(Request $request): ?AcademicYear
    {
        $id = $request->integer('year');

        return $id ? AcademicYear::query()->find($id) : AcademicYear::query()->where('is_current', true)->first();
    }

    /** @return Collection<int, Enrollment> */
    private function enrollments(AcademicYear $year, ?int $sectionId)
    {
        return Enrollment::query()
            ->with(['student.guardians', 'gradeLevel', 'section'])
            ->where('academic_year_id', $year->id)->where('status', 'active')
            ->when($sectionId, fn ($q, $id) => $q->where('section_id', $id))
            ->get()
            ->sortBy(fn (Enrollment $e) => [$e->gradeLevel->stage_id, $e->gradeLevel->sequence, $e->section?->name, $e->student->family_name_ar, $e->student->first_name_ar])
            ->values();
    }

    private function audit(Request $request, string $kind, AcademicYear $year): void
    {
        activity()->causedBy($request->user())->performedOn($year)->withProperties(['export' => $kind] + $request->only(['section_id', 'from', 'to', 'term_id']))->log('export');
    }
}
