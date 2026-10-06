<?php

namespace App\Http\Controllers\Web;

use App\Actions\Schools\AddSchoolMember;
use App\Enums\SchoolRole;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Auth\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Accepting an invitation: new people set a password; existing accounts confirm theirs. */
class InvitationController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = Invitation::findPending($token);
        abort_if($invitation === null, 404);

        return Inertia::render('Auth/AcceptInvitation', [
            'token' => $token,
            'school' => $invitation->school->name,
            'name' => $invitation->name,
            'email' => $invitation->email,
            'hasAccount' => User::query()->where('email', $invitation->email)->exists(),
        ]);
    }

    public function accept(Request $request, string $token, AddSchoolMember $addMember, TwoFactor $twoFactor): RedirectResponse
    {
        $invitation = Invitation::findPending($token);
        abort_if($invitation === null, 404);

        $user = User::query()->where('email', $invitation->email)->first();

        if ($user) {
            $data = $request->validate(['password' => ['required', 'string']]);
            if (! Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages(['password' => __('auth.password')]);
            }
        } else {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:150'],
                'password' => ['required', 'confirmed', PasswordRule::min(8)],
            ]);
        }

        DB::transaction(function () use (&$user, $data, $invitation, $addMember) {
            $user ??= User::query()->create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => Hash::make($data['password']),
                'locale' => $invitation->school->default_locale,
            ]);
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();

            $addMember->handle($invitation->school, $user, ...array_map(fn ($r) => SchoolRole::from($r), $invitation->roles));

            // Saved without model events: the guest accepting has no school context yet.
            $invitation->forceFill(['accepted_at' => now()])->saveQuietly();
        });

        $request->session()->put('school_id', $invitation->school_id);

        // Existing accounts with two-factor on still have to pass the code step.
        if ($twoFactor->enabled($user)) {
            $request->session()->put('login.id', $user->id);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('school_id', $invitation->school_id);

        return redirect()->route('dashboard');
    }
}
