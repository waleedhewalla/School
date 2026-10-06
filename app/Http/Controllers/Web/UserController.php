<?php

namespace App\Http\Controllers\Web;

use App\Enums\SchoolRole;
use App\Http\Controllers\Controller;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/** The school's user accounts: members with their roles, and pending invitations. */
class UserController extends Controller
{
    public function index(CurrentSchool $currentSchool): Response
    {
        $school = $currentSchool->get();
        $rolesByUser = Role::query()
            ->join(config('permission.table_names.model_has_roles').' as mhr', 'mhr.role_id', '=', 'roles.id')
            ->where('mhr.school_id', $school->id)
            ->where('mhr.model_type', (new User)->getMorphClass())
            ->get(['roles.name', 'mhr.model_id'])
            ->groupBy('model_id');

        return Inertia::render('Users/Index', [
            'members' => Membership::query()->with('user')->where('school_id', $school->id)->get()
                ->sortBy(fn (Membership $m) => $m->user->name)->values()
                ->map(fn (Membership $m) => [
                    'user_id' => $m->user_id,
                    'name' => $m->user->name,
                    'email' => $m->user->email,
                    'status' => $m->status,
                    'roles' => $rolesByUser->get($m->user_id, collect())->pluck('name')->values(),
                    'two_factor' => $m->user->two_factor_confirmed_at !== null,
                ]),
            'invitations' => Invitation::query()->whereNull('accepted_at')->where('expires_at', '>', now())->latest()->get()
                ->map(fn (Invitation $i) => ['id' => $i->id, 'name' => $i->name, 'email' => $i->email, 'roles' => $i->roles, 'expires_at' => $i->expires_at->toDateString()]),
            'roles' => collect(SchoolRole::cases())->map->value,
        ]);
    }

    public function invite(Request $request, CurrentSchool $currentSchool): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::enum(SchoolRole::class)],
        ]);
        $school = $currentSchool->get();
        $this->ensureCanGrant($request->user(), $data['roles']);

        $alreadyMember = Membership::query()->where('school_id', $school->id)
            ->whereHas('user', fn ($q) => $q->where('email', $data['email']))->exists();
        if ($alreadyMember) {
            throw ValidationException::withMessages(['email' => __('This person is already a member of the school.')]);
        }

        [$invitation, $token] = Invitation::issue($data + ['invited_by' => $request->user()->id]);
        Mail::to($data['email'])->send(new InvitationMail($invitation, route('invitations.show', $token), $school->name_ar, $school->default_locale));

        return back()->with('success', __('Invitation sent.'));
    }

    public function revoke(Invitation $invitation): RedirectResponse
    {
        $invitation->delete();

        return back()->with('success', __('Invitation cancelled.'));
    }

    /** Replace a member's roles in this school, or deactivate/reactivate them. */
    public function update(Request $request, User $user, CurrentSchool $currentSchool): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => [Rule::enum(SchoolRole::class)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $membership = Membership::query()->where('school_id', $currentSchool->id())->where('user_id', $user->id)->firstOrFail();

        if ($user->is($request->user()) && (($data['status'] ?? 'active') === 'inactive' || (isset($data['roles']) && ! in_array(SchoolRole::SchoolAdmin->value, $data['roles'], true)))) {
            throw ValidationException::withMessages(['roles' => __('You cannot remove your own admin access.')]);
        }

        $this->ensureCanGrant($request->user(), $data['roles'] ?? []);

        if (isset($data['roles'])) {
            $user->unsetRelation('roles');
            $user->syncRoles($data['roles']);
        }
        if (isset($data['status'])) {
            $membership->update(['status' => $data['status']]);
        }

        return back()->with('success', __('Changes saved.'));
    }

    /** Nobody can hand out a role carrying permissions they don't hold themselves. */
    private function ensureCanGrant(User $actor, array $roles): void
    {
        $mine = $actor->getAllPermissions()->pluck('name');

        foreach (Role::query()->whereIn('name', $roles)->with('permissions')->get() as $role) {
            if ($role->permissions->pluck('name')->diff($mine)->isNotEmpty()) {
                throw ValidationException::withMessages(['roles' => __('You cannot grant a role with more access than your own.')]);
            }
        }
    }
}
