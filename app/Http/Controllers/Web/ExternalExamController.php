<?php

namespace App\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\ExternalExam;
use App\Models\ExternalExamResult;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\Subject;
use App\Rules\ExistsInCurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Reader\XLSX\Reader;

/**
 * National and external tests (Nafes, Qiyas): record the school's results
 * by hand or from the Excel the school receives, and compare sections.
 */
class ExternalExamController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::GradesView);

        return Inertia::render('ExternalExams/Index', [
            'exams' => ExternalExam::query()->with('gradeLevel', 'subject')->withCount('results')->withAvg('results', 'score')
                ->orderByDesc('held_on')->orderByDesc('id')->get()
                ->map(fn (ExternalExam $e) => [
                    'id' => $e->id, 'name' => $e->name, 'type' => $e->type, 'held_on' => $e->held_on?->toDateString(),
                    'grade' => $e->gradeLevel?->name, 'subject' => $e->subject?->name, 'max' => $e->max_score,
                    'count' => $e->results_count, 'average' => $e->results_avg_score !== null ? round((float) $e->results_avg_score, 1) : null,
                ]),
            'grades' => GradeLevel::query()->orderBy('stage_id')->orderBy('sequence')->get()->map(fn (GradeLevel $g) => ['id' => $g->id, 'name' => $g->name]),
            'subjects' => Subject::query()->ordered()->get()->map(fn (Subject $s) => ['id' => $s->id, 'name' => $s->name]),
            'canManage' => $request->user()->can(Permission::GradesManage),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::GradesManage);
        $year = AcademicYear::query()->where('is_current', true)->firstOrFail();
        $exam = ExternalExam::query()->create($request->validate([
            'type' => ['required', Rule::in(ExternalExam::TYPES)],
            'name' => ['required', 'string', 'max:200'],
            'held_on' => ['nullable', 'date'],
            'grade_level_id' => ['nullable', 'integer', ExistsInCurrentSchool::inTable('grade_levels')],
            'subject_id' => ['nullable', 'integer', ExistsInCurrentSchool::in('subjects')],
            'max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
        ]) + ['academic_year_id' => $year->id]);

        return redirect()->route('external-exams.show', $exam);
    }

    public function show(Request $request, ExternalExam $exam): Response
    {
        Gate::authorize(Permission::GradesView);
        $exam->load('gradeLevel', 'subject');
        $results = $exam->results()->pluck('score', 'student_id');
        $enrollments = Enrollment::query()->with('student', 'section')
            ->where('academic_year_id', $exam->academic_year_id)->where('status', 'active')
            ->when($exam->grade_level_id, fn ($q, $id) => $q->where('grade_level_id', $id))
            ->get()->sortBy(fn (Enrollment $e) => [$e->section?->name, $e->student->first_name_ar])->values();

        $bySection = $enrollments->groupBy(fn (Enrollment $e) => $e->section?->name ?? '—')
            ->map(fn ($list, $name) => [
                'section' => $name,
                'count' => $list->filter(fn (Enrollment $e) => $results->has($e->student_id))->count(),
                'average' => ($scores = $list->map(fn (Enrollment $e) => $results->get($e->student_id))->filter(fn ($v) => $v !== null))->isNotEmpty()
                    ? round($scores->avg(), 1) : null,
            ])->values();

        // Spread of results as a share of the maximum, in five bands.
        $bands = collect([[90, 100], [75, 89.99], [60, 74.99], [50, 59.99], [0, 49.99]])->map(fn ($b) => [
            'from' => $b[0], 'to' => $b[0] === 90 ? 100 : (int) ceil($b[1]),
            'count' => $results->filter(fn ($s) => ($p = $s / $exam->max_score * 100) >= $b[0] && $p <= $b[1])->count(),
        ]);

        return Inertia::render('ExternalExams/Show', [
            'exam' => ['id' => $exam->id, 'name' => $exam->name, 'type' => $exam->type, 'max' => $exam->max_score,
                'grade' => $exam->gradeLevel?->name, 'subject' => $exam->subject?->name, 'held_on' => $exam->held_on?->toDateString()],
            'students' => $enrollments->map(fn (Enrollment $e) => [
                'id' => $e->student_id, 'name' => $e->student->name, 'section' => $e->section?->name, 'score' => $results->get($e->student_id),
            ]),
            'bySection' => $bySection,
            'bands' => $bands,
            'canManage' => $request->user()->can(Permission::GradesManage),
        ]);
    }

    public function saveResults(Request $request, ExternalExam $exam): RedirectResponse
    {
        Gate::authorize(Permission::GradesManage);
        $data = $request->validate([
            'scores' => ['required', 'array', 'max:2000'],
            'scores.*' => ['nullable', 'numeric', 'min:0', 'max:'.$exam->max_score],
        ]);
        $valid = Student::query()->whereKey(array_keys($data['scores']))->pluck('id')->flip();

        DB::transaction(function () use ($data, $exam, $valid) {
            foreach ($data['scores'] as $studentId => $score) {
                if (! $valid->has((int) $studentId)) {
                    continue;
                }
                $score === null || $score === ''
                    ? ExternalExamResult::query()->where('external_exam_id', $exam->id)->where('student_id', $studentId)->delete()
                    : ExternalExamResult::query()->updateOrCreate(['external_exam_id' => $exam->id, 'student_id' => $studentId], ['score' => $score]);
            }
        });

        return back()->with('success', __('Changes saved.'));
    }

    /** Excel with a student column (national ID or student number) and a score column. */
    public function import(Request $request, ExternalExam $exam): RedirectResponse
    {
        Gate::authorize(Permission::GradesManage);
        $file = $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']])['file'];

        $reader = new Reader;
        $reader->open($file->getRealPath());
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row->toArray());
            }
            break;
        }
        $reader->close();

        $students = Student::query()->get(['id', 'national_id', 'student_number']);
        $byId = $students->filter(fn ($s) => $s->national_id)->keyBy('national_id');
        $byNumber = $students->keyBy('student_number');
        [$saved, $skipped] = [0, 0];

        DB::transaction(function () use ($rows, $exam, $byId, $byNumber, &$saved, &$skipped) {
            foreach ($rows as $cells) {
                $key = (string) ($cells[0] ?? '');
                $score = $cells[1] ?? null;
                if (! is_numeric($score)) {
                    continue; // header or blank row
                }
                $student = $byId->get($key) ?? $byNumber->get($key) ?? $byNumber->get(str_pad($key, 6, '0', STR_PAD_LEFT));
                if ($student === null || $score < 0 || $score > $exam->max_score) {
                    $skipped++;

                    continue;
                }
                ExternalExamResult::query()->updateOrCreate(['external_exam_id' => $exam->id, 'student_id' => $student->id], ['score' => $score]);
                $saved++;
            }
        });

        if ($saved === 0) {
            throw ValidationException::withMessages(['file' => __('exams.nothing_imported')]);
        }

        return back()->with('success', __('exams.imported', ['saved' => $saved, 'skipped' => $skipped]));
    }

    public function destroy(ExternalExam $exam): RedirectResponse
    {
        Gate::authorize(Permission::GradesManage);
        $exam->delete();

        return redirect()->route('external-exams.index')->with('success', __('Deleted.'));
    }
}
