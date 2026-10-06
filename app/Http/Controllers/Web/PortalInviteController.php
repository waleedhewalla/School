<?php

namespace App\Http\Controllers\Web;

use App\Enums\SchoolRole;
use App\Http\Controllers\Controller;
use App\Mail\InvitationMail;
use App\Models\Guardian;
use App\Models\Invitation;
use App\Models\Student;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/** Invites a guardian or a student to the portal by email; accepting links their record. */
class PortalInviteController extends Controller
{
    public function guardian(Request $request, Guardian $guardian, CurrentSchool $currentSchool): RedirectResponse
    {
        return $this->invite($request, $currentSchool, $guardian->user_id, $guardian->name_ar, SchoolRole::Guardian, ['guardian_id' => $guardian->id]);
    }

    public function student(Request $request, Student $student, CurrentSchool $currentSchool): RedirectResponse
    {
        return $this->invite($request, $currentSchool, $student->user_id, $student->name_ar, SchoolRole::Student, ['student_id' => $student->id]);
    }

    /** Invites every guardian of the current year's students who has an email and no login yet. */
    public function allGuardians(Request $request, CurrentSchool $currentSchool): RedirectResponse
    {
        $school = $currentSchool->get();
        $count = 0;
        Guardian::query()->whereNull('user_id')->whereNotNull('email')
            ->whereHas('students.currentEnrollment')
            ->whereDoesntHave('invitations', fn ($q) => $q->whereNull('accepted_at')->where('expires_at', '>', now()))
            ->each(function (Guardian $guardian) use ($request, $school, &$count) {
                [$invitation, $token] = Invitation::issue([
                    'email' => $guardian->email, 'name' => $guardian->name_ar, 'roles' => [SchoolRole::Guardian->value],
                    'guardian_id' => $guardian->id, 'invited_by' => $request->user()->id,
                ]);
                Mail::to($guardian->email)->send(new InvitationMail($invitation, route('invitations.show', $token), $school->name_ar, $school->default_locale));
                $count++;
            });

        return back()->with('success', __('portal.invited_many', ['count' => $count]));
    }

    /** @param  array<string, int>  $link */
    private function invite(Request $request, CurrentSchool $currentSchool, ?int $existingUser, string $name, SchoolRole $role, array $link): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        if ($existingUser !== null) {
            throw ValidationException::withMessages(['email' => __('portal.already_linked')]);
        }

        $school = $currentSchool->get();
        Invitation::query()->where($link)->whereNull('accepted_at')->delete();
        [$invitation, $token] = Invitation::issue(['email' => $data['email'], 'name' => $name, 'roles' => [$role->value], 'invited_by' => $request->user()->id] + $link);
        Mail::to($data['email'])->send(new InvitationMail($invitation, route('invitations.show', $token), $school->name_ar, $school->default_locale));

        return back()->with('success', __('Invitation sent.'));
    }
}
