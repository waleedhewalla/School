<?php

namespace App\Http\Controllers\Web;

use App\Actions\Schools\AddSchoolMember;
use App\Actions\Schools\CreateSchool;
use App\Enums\DateDisplay;
use App\Enums\SchoolRole;
use App\Http\Controllers\Controller;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\School;
use App\Models\User;
use App\Support\Locale;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The SaaS operator's console: every school, its size and status,
 * onboarding new schools and suspending or reactivating them.
 */
class PlatformController extends Controller
{
    public function index(): Response
    {
        // Counts read across schools on purpose, so they go through the query builder.
        $students = DB::table('students')->whereNull('deleted_at')->where('status', 'active')
            ->selectRaw('school_id, count(*) as total')->groupBy('school_id')->pluck('total', 'school_id');
        $members = DB::table('memberships')->where('status', 'active')
            ->selectRaw('school_id, count(*) as total')->groupBy('school_id')->pluck('total', 'school_id');
        $applications = DB::table('applications')
            ->selectRaw('school_id, count(*) as total')->groupBy('school_id')->pluck('total', 'school_id');
        $lastActivity = DB::table('attendance_records')
            ->selectRaw('school_id, max(date) as last')->groupBy('school_id')->pluck('last', 'school_id');

        $schools = School::query()->orderBy('name_ar')->get()->map(fn (School $s) => [
            ...$s->only(['id', 'slug', 'name_ar', 'name_en', 'status', 'ministry_code']),
            'created_at' => $s->created_at->toDateString(),
            'students' => (int) ($students[$s->id] ?? 0),
            'members' => (int) ($members[$s->id] ?? 0),
            'applications' => (int) ($applications[$s->id] ?? 0),
            'last_attendance' => $lastActivity[$s->id] ?? null,
        ]);

        return Inertia::render('Platform/Index', [
            'schools' => $schools,
            'totals' => [
                'schools' => $schools->count(),
                'active' => $schools->where('status', 'active')->count(),
                'students' => $schools->sum('students'),
                'members' => $schools->sum('members'),
            ],
        ]);
    }

    public function store(Request $request, CreateSchool $createSchool, AddSchoolMember $addMember, CurrentSchool $currentSchool): RedirectResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'alpha_dash:ascii', 'regex:/^[a-z][a-z0-9-]*$/', 'max:60', Rule::unique('schools', 'slug')],
            'name_ar' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'default_locale' => ['required', Rule::in(Locale::supported())],
            'date_display' => ['required', Rule::enum(DateDisplay::class)],
            'ministry_code' => ['nullable', 'string', 'max:30'],
            'admin_name' => ['required', 'string', 'max:150'],
            'admin_email' => ['required', 'email', 'max:255'],
        ]);

        $school = DB::transaction(function () use ($data, $createSchool, $addMember, $currentSchool, $request) {
            $school = $createSchool->handle(collect($data)->except(['admin_name', 'admin_email'])->all());
            $existing = User::query()->where('email', $data['admin_email'])->first();

            if ($existing) {
                $addMember->handle($school, $existing, SchoolRole::SchoolAdmin);
            } else {
                $currentSchool->run($school, function () use ($data, $school, $request) {
                    [$invitation, $token] = Invitation::issue([
                        'name' => $data['admin_name'], 'email' => $data['admin_email'],
                        'roles' => [SchoolRole::SchoolAdmin->value], 'invited_by' => $request->user()->id,
                    ]);
                    Mail::to($data['admin_email'])->send(new InvitationMail($invitation, route('invitations.show', $token), $school->name_ar, $school->default_locale));
                });
            }

            return $school;
        });

        activity()->causedBy($request->user())->performedOn($school)->log('school onboarded');

        return back()->with('success', __('platform.created', ['school' => $school->name_ar]));
    }

    public function update(Request $request, School $school): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended'])]]);
        $school->update($data);
        activity()->causedBy($request->user())->performedOn($school)->log('school '.$data['status']);

        return back()->with('success', __('Changes saved.'));
    }

    /** Opens the school as the platform admin (support work). */
    public function enter(Request $request, School $school): RedirectResponse
    {
        abort_unless($request->user()->canEnterSchool($school), 403, __('tenancy.not_a_member'));
        $request->session()->put('school_id', $school->id);
        activity()->causedBy($request->user())->performedOn($school)->log('platform admin entered school');

        return redirect()->route('dashboard');
    }
}
