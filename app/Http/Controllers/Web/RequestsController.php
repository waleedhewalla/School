<?php

namespace App\Http\Controllers\Web;

use App\Actions\Requests\ApplyAbsenceExcuse;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AbsenceExcuse;
use App\Models\LeaveRequest;
use App\Models\StaffMember;
use App\Models\Student;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Requests that need someone's approval: guardians' absence excuses
 * (reviewed by attendance managers) and staff leave (reviewed by staff
 * managers).
 */
class RequestsController extends Controller
{
    // --- Absence excuses -------------------------------------------------

    public function myExcuses(Request $request): Response
    {
        $children = Student::query()->guardedBy($request->user())->get();

        return Inertia::render('Requests/MyExcuses', [
            'children' => $children->map(fn (Student $s) => ['id' => $s->id, 'name' => $s->name]),
            'excuses' => AbsenceExcuse::query()->with('student')->whereIn('student_id', $children->pluck('id'))
                ->latest()->limit(30)->get()->map(fn (AbsenceExcuse $e) => self::presentExcuse($e)),
        ]);
    }

    public function storeExcuse(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'from_date' => ['required', 'date', 'after_or_equal:'.now()->subDays(30)->toDateString()],
            'to_date' => ['required', 'date', 'after_or_equal:from_date', 'before_or_equal:'.now()->addDays(30)->toDateString()],
            'reason' => ['required', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], ['from_date.after_or_equal' => __('attendance.excuse_dates'), 'to_date.before_or_equal' => __('attendance.excuse_dates')]);

        // Only for the guardian's own children.
        abort_unless(Student::query()->guardedBy($request->user())->whereKey($data['student_id'])->exists(), 403);

        $excuse = new AbsenceExcuse(collect($data)->except('attachment')->all() + ['submitted_by' => $request->user()->id]);
        $this->attach($excuse, $request->file('attachment'), 'excuses');
        $excuse->save();

        return back()->with('success', __('attendance.excuse_submitted'));
    }

    public function excuses(): Response
    {
        Gate::authorize(Permission::AttendanceManage);

        return Inertia::render('Requests/Excuses', [
            'pending' => AbsenceExcuse::query()->with('student.currentEnrollment.section.gradeLevel', 'submitter')->pending()
                ->oldest()->get()->map(fn (AbsenceExcuse $e) => self::presentExcuse($e)),
            'recent' => AbsenceExcuse::query()->with('student', 'reviewer')->where('status', '!=', AbsenceExcuse::PENDING)
                ->latest('reviewed_at')->limit(30)->get()->map(fn (AbsenceExcuse $e) => self::presentExcuse($e)),
        ]);
    }

    public function reviewExcuse(Request $request, AbsenceExcuse $excuse, ApplyAbsenceExcuse $apply): RedirectResponse
    {
        Gate::authorize(Permission::AttendanceManage);
        $data = $this->decision($request, $excuse);

        DB::transaction(function () use ($excuse, $data, $request, $apply) {
            $excuse->review($data['status'], $request->user(), $data['note'] ?? null);
            if ($data['status'] === AbsenceExcuse::APPROVED) {
                $apply->handle($excuse);
            }
        });

        return back()->with('success', __('attendance.excuse_reviewed'));
    }

    public function excuseAttachment(Request $request, AbsenceExcuse $excuse): StreamedResponse
    {
        $allowed = $request->user()->can(Permission::AttendanceManage)
            || Student::query()->guardedBy($request->user())->whereKey($excuse->student_id)->exists();
        abort_unless($allowed && $excuse->attachment_path, 404);

        return Storage::disk('local')->download($excuse->attachment_path, $excuse->attachment_name);
    }

    // --- Staff leave -----------------------------------------------------

    public function myLeave(Request $request): Response
    {
        $staff = StaffMember::query()->where('user_id', $request->user()->id)->first();

        return Inertia::render('Requests/MyLeave', [
            'hasStaffRecord' => $staff !== null,
            'types' => LeaveRequest::TYPES,
            'requests' => $staff ? LeaveRequest::query()->where('staff_member_id', $staff->id)->latest()->limit(30)->get()
                ->map(fn (LeaveRequest $l) => self::presentLeave($l)) : [],
            'takenThisYear' => $staff ? LeaveRequest::query()->where('staff_member_id', $staff->id)->where('status', LeaveRequest::APPROVED)
                ->whereYear('from_date', now()->year)->get()->groupBy('type')->map(fn ($list) => $list->sum(fn (LeaveRequest $l) => $l->days())) : [],
        ]);
    }

    public function storeLeave(Request $request): RedirectResponse
    {
        $staff = StaffMember::query()->where('user_id', $request->user()->id)->first();
        if ($staff === null) {
            throw ValidationException::withMessages(['type' => __('attendance.no_staff_record')]);
        }
        $data = $request->validate([
            'type' => ['required', Rule::in(LeaveRequest::TYPES)],
            'from_date' => ['required', 'date', 'after_or_equal:'.now()->subDays(30)->toDateString()],
            'to_date' => ['required', 'date', 'after_or_equal:from_date', 'before_or_equal:'.now()->addYear()->toDateString()],
            'reason' => ['nullable', 'string', 'max:1000', 'required_if:type,emergency,other'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $leave = new LeaveRequest(collect($data)->except('attachment')->all() + ['staff_member_id' => $staff->id]);
        $this->attach($leave, $request->file('attachment'), 'leave');
        $leave->save();

        return back()->with('success', __('attendance.leave_submitted'));
    }

    public function leave(): Response
    {
        Gate::authorize(Permission::StaffManage);

        return Inertia::render('Requests/Leave', [
            'pending' => LeaveRequest::query()->with('staffMember')->pending()->oldest()->get()->map(fn (LeaveRequest $l) => self::presentLeave($l)),
            'recent' => LeaveRequest::query()->with('staffMember', 'reviewer')->where('status', '!=', LeaveRequest::PENDING)
                ->latest('reviewed_at')->limit(30)->get()->map(fn (LeaveRequest $l) => self::presentLeave($l)),
            // Who is away today, for cover planning.
            'awayToday' => LeaveRequest::query()->with('staffMember')->where('status', LeaveRequest::APPROVED)
                ->where('from_date', '<=', today()->toDateString())->where('to_date', '>=', today()->toDateString())->get()
                ->map(fn (LeaveRequest $l) => ['name' => $l->staffMember->name, 'type' => $l->type, 'to_date' => $l->to_date->toDateString()]),
        ]);
    }

    public function reviewLeave(Request $request, LeaveRequest $leave): RedirectResponse
    {
        Gate::authorize(Permission::StaffManage);
        $data = $this->decision($request, $leave);
        $leave->review($data['status'], $request->user(), $data['note'] ?? null);

        return back()->with('success', __('attendance.leave_reviewed'));
    }

    public function leaveAttachment(Request $request, LeaveRequest $leave): StreamedResponse
    {
        $own = $leave->staffMember->user_id === $request->user()->id;
        abort_unless(($own || $request->user()->can(Permission::StaffManage)) && $leave->attachment_path, 404);

        return Storage::disk('local')->download($leave->attachment_path, $leave->attachment_name);
    }

    // --- Shared ----------------------------------------------------------

    /** @return array{status: string, note?: string|null} */
    private function decision(Request $request, AbsenceExcuse|LeaveRequest $item): array
    {
        if ($item->status !== AbsenceExcuse::PENDING) {
            throw ValidationException::withMessages(['status' => __('attendance.already_reviewed')]);
        }

        return $request->validate([
            'status' => ['required', Rule::in([AbsenceExcuse::APPROVED, AbsenceExcuse::REJECTED])],
            'note' => ['nullable', 'string', 'max:300', 'required_if:status,rejected'],
        ]);
    }

    private function attach(AbsenceExcuse|LeaveRequest $item, ?UploadedFile $file, string $folder): void
    {
        if ($file) {
            $item->attachment_path = $file->store("{$folder}/".app(CurrentSchool::class)->id(), 'local');
            $item->attachment_name = mb_substr($file->getClientOriginalName(), 0, 200);
        }
    }

    /** @return array<string, mixed> */
    public static function presentExcuse(AbsenceExcuse $e): array
    {
        $enrollment = $e->student->relationLoaded('currentEnrollment') ? $e->student->currentEnrollment : null;

        return [
            'id' => $e->id,
            'student' => $e->student->name,
            'class' => $enrollment?->section ? $enrollment->section->gradeLevel->name.' / '.$enrollment->section->name : null,
            'from_date' => $e->from_date->toDateString(),
            'to_date' => $e->to_date->toDateString(),
            'reason' => $e->reason,
            'status' => $e->status,
            'review_note' => $e->review_note,
            'by' => $e->relationLoaded('submitter') ? $e->submitter?->name : null,
            'reviewer' => $e->relationLoaded('reviewer') ? $e->reviewer?->name : null,
            'attachment' => $e->attachment_name ? ['name' => $e->attachment_name, 'url' => "/excuses/{$e->id}/attachment"] : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function presentLeave(LeaveRequest $l): array
    {
        return [
            'id' => $l->id,
            'staff' => $l->relationLoaded('staffMember') ? $l->staffMember->name : null,
            'type' => $l->type,
            'from_date' => $l->from_date->toDateString(),
            'to_date' => $l->to_date->toDateString(),
            'days' => $l->days(),
            'reason' => $l->reason,
            'status' => $l->status,
            'review_note' => $l->review_note,
            'reviewer' => $l->relationLoaded('reviewer') ? $l->reviewer?->name : null,
            'attachment' => $l->attachment_name ? ['name' => $l->attachment_name, 'url' => "/leave/{$l->id}/attachment"] : null,
        ];
    }
}
