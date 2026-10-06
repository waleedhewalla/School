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

        // Guardians without staff permissions land on their children.
        if (! $user->can(Permission::StudentsView) && ! $user->can(Permission::AttendanceRecord)) {
            return redirect()->route('portal.children');
        }

        $year = AcademicYear::query()->where('is_current', true)->first();
        $today = now($currentSchool->get()->timezone)->toDateString();

        $daily = AttendanceRecord::query()
            ->with('code')
            ->where('date', $today)
            ->where('period', AttendanceRecord::DAILY)
            ->get();

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

        $since = now($school->timezone)->subDays(30);
        $recent = AttendanceRecord::query()->with('code')
            ->where('period', AttendanceRecord::DAILY)
            ->where('date', '>=', $since->toDateString())
            ->get();
        $inSchool = $recent->filter(fn ($r) => in_array($r->code->kind, [AttendanceKind::Present, AttendanceKind::Late], true))->count();
        $absences = $recent->filter(fn ($r) => $r->code->kind === AttendanceKind::Absent)->countBy('student_id')
            ->filter(fn ($n) => $n >= 3)->sortDesc()->take(8);
        $atRisk = Student::query()->whereKey($absences->keys())->get()->keyBy('id');

        return Inertia::render('Dashboard', [
            'attendanceRate' => $recent->isEmpty() ? null : round($inSchool / $recent->count() * 100, 1),
            'atRisk' => $absences->map(fn ($n, $id) => ['student_id' => $id, 'name' => $atRisk[$id]?->name, 'absences' => $n])->values(),
            'announcements' => Announcement::query()->with('section.gradeLevel', 'author')->published()->forStaff()->limit(3)->get()
                ->map(fn (Announcement $a) => AnnouncementController::present($a)),
            'lessonsToday' => $lessonsToday,
            'today' => SchoolDate::display(now(), $currentSchool->get()->date_display),
            'year' => $year?->only(['id', 'name']),
            'stats' => [
                'students' => $year ? Enrollment::query()->where('academic_year_id', $year->id)->where('status', EnrollmentStatus::Active)->count() : 0,
                'sections' => $sectionIds->count(),
                'registers_taken' => $daily->pluck('section_id')->unique()->count(),
                'absent_today' => $daily->filter(fn ($r) => $r->code->kind === AttendanceKind::Absent)->count(),
                'late_today' => $daily->filter(fn ($r) => $r->code->kind === AttendanceKind::Late)->count(),
            ],
        ]);
    }
}
