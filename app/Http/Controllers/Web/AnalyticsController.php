<?php

namespace App\Http\Controllers\Web;

use App\Enums\AttendanceKind;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\ClinicVisit;
use App\Models\Enrollment;
use App\Models\LibraryLoan;
use App\Models\Section;
use App\Models\Term;
use App\Support\AttendanceSummary;
use App\Support\BehaviourScore;
use App\Support\Grades\TermResults;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * School analytics for management: enrolment, attendance trends, term
 * results by subject, behaviour, and the students who need follow-up.
 */
class AnalyticsController extends Controller
{
    /** Attendance below this share of recorded days flags a student. */
    public const ATTENDANCE_FLAG = 90.0;

    /** A behaviour score below this flags a student. */
    public const BEHAVIOUR_FLAG = 80;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can(Permission::AttendanceManage) || $user->can(Permission::GradesManage), 403);

        $year = AcademicYear::query()->where('is_current', true)->first();
        if ($year === null) {
            return Inertia::render('Analytics/Index', ['empty' => true]);
        }

        $enrollments = Enrollment::query()->with('student', 'gradeLevel', 'section')
            ->where('academic_year_id', $year->id)->where('status', 'active')->get();
        $ids = $enrollments->pluck('student_id');
        $today = CarbonImmutable::today();

        $attendance = AttendanceSummary::forStudents($ids, $today->subDays(30), $today);
        $behaviour = BehaviourScore::forStudents($ids, $year->id);

        // Term: the one asked for, else the current one by date, else the latest.
        $terms = $year->terms()->orderBy('sequence')->get();
        $term = $terms->firstWhere('id', $request->integer('term'))
            ?? $terms->first(fn (Term $t) => $today->betweenIncluded($t->starts_on, $t->ends_on))
            ?? $terms->last();
        [$subjectAverages, $failing] = $term ? $this->termResults($year, $term) : [collect(), collect()];

        $seeGrades = $user->can(Permission::GradesView);
        $seeBehaviour = $user->can(Permission::BehaviourManage);
        if (! $seeGrades) {
            [$subjectAverages, $failing] = [collect(), collect()];
        }

        $atRisk = $enrollments->map(function (Enrollment $e) use ($attendance, $behaviour, $failing, $seeBehaviour) {
            $reasons = [];
            $a = $attendance[$e->student_id];
            $rate = AttendanceSummary::rate($a);
            if ($rate !== null && $a['recorded'] >= 5 && $rate < self::ATTENDANCE_FLAG) {
                $reasons[] = ['kind' => 'attendance', 'value' => $rate];
            }
            if ($seeBehaviour && ($score = $behaviour[$e->student_id]['score']) < self::BEHAVIOUR_FLAG) {
                $reasons[] = ['kind' => 'behaviour', 'value' => $score];
            }
            if (($count = $failing->get($e->student_id, 0)) >= 1) {
                $reasons[] = ['kind' => 'failing', 'value' => $count];
            }

            return $reasons ? [
                'id' => $e->student_id, 'name' => $e->student->name,
                'class' => $e->gradeLevel->name.($e->section ? ' / '.$e->section->name : ''), 'reasons' => $reasons,
            ] : null;
        })->filter()->sortByDesc(fn ($r) => count($r['reasons']))->values();

        $rates = $attendance->map(fn ($a) => AttendanceSummary::rate($a))->filter(fn ($r) => $r !== null);

        return Inertia::render('Analytics/Index', [
            'empty' => false,
            'year' => $year->name,
            'tiles' => [
                'students' => $enrollments->count(),
                'boys' => $enrollments->filter(fn (Enrollment $e) => $e->student->gender->value === 'male')->count(),
                'attendance' => $rates->isNotEmpty() ? round($rates->avg(), 1) : null,
                'behaviour' => $seeBehaviour && $behaviour->isNotEmpty() ? round($behaviour->avg('score'), 1) : null,
                'at_risk' => $atRisk->count(),
            ],
            'byGrade' => $enrollments->groupBy('grade_level_id')
                ->map(fn (Collection $list) => ['label' => $list->first()->gradeLevel->name, 'value' => $list->count(), 'order' => [$list->first()->gradeLevel->stage_id, $list->first()->gradeLevel->sequence]])
                ->sortBy('order')->values()->map(fn ($r) => ['label' => $r['label'], 'value' => $r['value']]),
            'weekly' => $this->weeklyAttendance($today),
            'bySection' => $this->sectionAttendance($enrollments, $attendance),
            'terms' => $terms->map(fn (Term $t) => ['id' => $t->id, 'name' => $t->name]),
            'termId' => $term?->id,
            'subjects' => $subjectAverages,
            'seeGrades' => $seeGrades,
            'atRisk' => $atRisk->take(50),
            'admissions' => Application::query()->whereHas('window', fn ($q) => $q->where('academic_year_id', '>=', $year->id))
                ->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'services' => [
                'clinic_visits' => ClinicVisit::query()->where('visited_at', '>=', $today->subDays(30))->count(),
                'overdue_books' => LibraryLoan::query()->whereNull('returned_on')->where('due_on', '<', $today->toDateString())->count(),
            ],
        ]);
    }

    /** @return list<array{label: string, value: float|null, days: int}> present-or-late share of daily marks, last 8 weeks */
    private function weeklyAttendance(CarbonImmutable $today): array
    {
        $from = $today->subWeeks(8)->startOfWeek(CarbonImmutable::SUNDAY);
        $rows = AttendanceRecord::query()
            ->join('attendance_codes', 'attendance_codes.id', '=', 'attendance_records.attendance_code_id')
            ->where('attendance_records.period', AttendanceRecord::DAILY)
            ->where('attendance_records.date', '>=', $from->toDateString())
            ->selectRaw('attendance_records.date as day, attendance_codes.kind, count(*) as total')
            ->groupBy('attendance_records.date', 'attendance_codes.kind')->get();

        $weeks = [];
        foreach ($rows as $row) {
            $week = CarbonImmutable::parse(substr((string) $row->day, 0, 10))->startOfWeek(CarbonImmutable::SUNDAY)->toDateString();
            $weeks[$week] ??= ['in' => 0, 'all' => 0, 'days' => []];
            $weeks[$week]['all'] += $row->total;
            $weeks[$week]['days'][substr((string) $row->day, 0, 10)] = true;
            if (in_array($row->kind, [AttendanceKind::Present->value, AttendanceKind::Late->value], true)) {
                $weeks[$week]['in'] += $row->total;
            }
        }
        ksort($weeks);

        return collect($weeks)->map(fn ($w, $week) => [
            'label' => $week, 'value' => $w['all'] ? round($w['in'] / $w['all'] * 100, 1) : null, 'days' => count($w['days']),
        ])->values()->all();
    }

    /** @return list<array{label: string, value: float}> lowest attendance first */
    private function sectionAttendance(Collection $enrollments, Collection $attendance): array
    {
        return $enrollments->filter(fn (Enrollment $e) => $e->section)->groupBy('section_id')->map(function (Collection $list) use ($attendance) {
            $sum = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'recorded' => 0];
            foreach ($list as $e) {
                foreach ($sum as $k => $_) {
                    $sum[$k] += $attendance[$e->student_id][$k];
                }
            }
            $first = $list->first();

            return ['label' => $first->gradeLevel->name.' / '.$first->section->name, 'value' => AttendanceSummary::rate($sum)];
        })->filter(fn ($r) => $r['value'] !== null)->sortBy('value')->values()->take(12)->all();
    }

    /** @return array{0: Collection, 1: Collection} subject averages, and failing-subject counts per student */
    private function termResults(AcademicYear $year, Term $term): array
    {
        $bySubject = [];
        $failing = collect();
        foreach (Section::query()->where('academic_year_id', $year->id)->get() as $section) {
            $results = TermResults::forSection($section, $term);
            $names = collect($results['subjects'])->pluck('name', 'id');
            foreach ($results['students'] as $row) {
                foreach ($row['subjects'] as $subjectId => $r) {
                    if ($r['complete'] && $r['percent'] !== null) {
                        $bySubject[$subjectId]['name'] = $names[$subjectId];
                        $bySubject[$subjectId]['scores'][] = $r['percent'];
                        if ($r['passed'] === false) {
                            $failing[$row['student_id']] = $failing->get($row['student_id'], 0) + 1;
                        }
                    }
                }
            }
        }

        return [
            collect($bySubject)->map(fn ($s) => ['label' => $s['name'], 'value' => round(array_sum($s['scores']) / count($s['scores']), 1), 'count' => count($s['scores'])])
                ->sortByDesc('value')->values(),
            $failing,
        ];
    }
}
