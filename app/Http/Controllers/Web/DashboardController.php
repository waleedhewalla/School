<?php

namespace App\Http\Controllers\Web;

use App\Enums\AttendanceKind;
use App\Enums\EnrollmentStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Models\TimetableEntry;
use App\Support\Dates\SchoolDate;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentSchool $currentSchool): Response|RedirectResponse
    {
        $user = $request->user();

        // Students land on their quizzes; guardians on their children.
        if (! $user->can(Permission::StudentsView) && ! $user->can(Permission::AttendanceRecord)) {
            return redirect()->route(Student::query()->where('user_id', $user->id)->exists() ? 'quizzes.mine' : 'portal.children');
        }

        $year = AcademicYear::query()->where('is_current', true)->first();
        $today = now($currentSchool->get()->timezone)->toDateString();

        // Counted in the database: a large school has tens of thousands of marks a month.
        $todayByKind = $this->countByKind(fn ($q) => $q->where('attendance_records.date', $today));
        $registersTaken = AttendanceRecord::query()->where('date', $today)->where('period', AttendanceRecord::DAILY)
            ->distinct()->count('section_id');

        $sectionIds = Section::query()->when($year, fn ($q) => $q->where('academic_year_id', $year->id))->pluck('id');

        $school = $currentSchool->get();
        $weekday = now($school->timezone)->dayOfWeek; // 0 = Sunday
        $lessonsToday = TimetableEntry::query()
            ->with(['period', 'section.gradeLevel', 'teachingAssignment.subject'])
            ->whereHas('staffMember', fn ($q) => $q->where('user_id', $user->id))
            ->where('day', $weekday)
            ->get()
            ->sortBy('period.sequence')
            ->values()
            ->map(fn (TimetableEntry $e) => [
                'period' => $e->period->name,
                'period_sequence' => $e->period->sequence,
                'time' => $e->period->startsAtShort().'–'.$e->period->endsAtShort(),
                'section_id' => $e->section_id,
                'section' => $e->section->gradeLevel->name.' / '.$e->section->name,
                'subject' => $e->teachingAssignment->subject->name,
                'room' => $e->room,
            ]);

        $since = now($school->timezone)->subDays(30)->toDateString();
        $recentByKind = $this->countByKind(fn ($q) => $q->where('attendance_records.date', '>=', $since));
        $recentTotal = array_sum($recentByKind);
        $inSchool = ($recentByKind[AttendanceKind::Present->value] ?? 0) + ($recentByKind[AttendanceKind::Late->value] ?? 0);

        $absences = AttendanceRecord::query()
            ->join('attendance_codes', 'attendance_codes.id', '=', 'attendance_records.attendance_code_id')
            ->where('attendance_records.period', AttendanceRecord::DAILY)
            ->where('attendance_records.date', '>=', $since)
            ->where('attendance_codes.kind', AttendanceKind::Absent->value)
            ->groupBy('attendance_records.student_id')
            ->havingRaw('count(*) >= 3')
            ->orderByRaw('count(*) desc')
            ->limit(8)
            ->selectRaw('attendance_records.student_id, count(*) as total')
            ->pluck('total', 'student_id')
            ->map(fn ($n) => (int) $n);
        $atRisk = Student::query()->whereKey($absences->keys())->get()->keyBy('id');

        return Inertia::render('Dashboard', [
            'attendanceRate' => $recentTotal === 0 ? null : round($inSchool / $recentTotal * 100, 1),
            'atRisk' => $absences->map(fn ($n, $id) => ['student_id' => $id, 'name' => $atRisk[$id]?->name, 'absences' => $n])->values(),
            'announcements' => Announcement::query()->with('section.gradeLevel', 'author')->published()->forStaff()->limit(3)->get()
                ->map(fn (Announcement $a) => AnnouncementController::present($a)),
            'lessonsToday' => $lessonsToday,
            'today' => SchoolDate::display(now(), $currentSchool->get()->date_display),
            'year' => $year?->only(['id', 'name']),
            'stats' => [
                'students' => $year ? Enrollment::query()->where('academic_year_id', $year->id)->where('status', EnrollmentStatus::Active)->count() : 0,
                'sections' => $sectionIds->count(),
                'registers_taken' => $registersTaken,
                'absent_today' => $todayByKind[AttendanceKind::Absent->value] ?? 0,
                'late_today' => $todayByKind[AttendanceKind::Late->value] ?? 0,
            ],
        ]);
    }

    /** @return array<string, int> daily-register marks per attendance kind */
    private function countByKind(callable $filter): array
    {
        $query = AttendanceRecord::query()
            ->join('attendance_codes', 'attendance_codes.id', '=', 'attendance_records.attendance_code_id')
            ->where('attendance_records.period', AttendanceRecord::DAILY);
        $filter($query);

        return $query->groupBy('attendance_codes.kind')
            ->selectRaw('attendance_codes.kind, count(*) as total')
            ->pluck('total', 'kind')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
