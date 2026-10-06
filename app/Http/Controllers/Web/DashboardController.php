<?php

namespace App\Http\Controllers\Web;

use App\Enums\AttendanceKind;
use App\Enums\EnrollmentStatus;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Section;
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

        return Inertia::render('Dashboard', [
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
