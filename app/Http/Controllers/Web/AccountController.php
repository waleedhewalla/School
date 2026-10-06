<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Auth\TwoFactor;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** The signed-in user's own settings: language, password, two-factor sign-in. */
class AccountController extends Controller
{
    public function show(Request $request, TwoFactor $twoFactor): Response
    {
        $user = $request->user();
        $pending = $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;

        return Inertia::render('Account/Show', [
            'twoFactor' => [
                'enabled' => $twoFactor->enabled($user),
                'pending' => $pending,
                'qr' => $pending ? $twoFactor->qrSvg($user) : null,
                'secret' => $pending ? $twoFactor->secret($user) : null,
            ],
            'recoveryCodes' => $request->session()->get('recovery_codes'),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $request->user()->update($request->validate([
            'name' => ['required', 'string', 'max:150'],
            'locale' => ['required', Rule::in(Locale::supported())],
        ]));
        $request->session()->put('locale', $request->user()->locale);

        return back()->with('success', __('Changes saved.'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', __('Password changed.'));
    }

    public function enableTwoFactor(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $twoFactor->begin($request->user());

        return back();
    }

    public function confirmTwoFactor(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $codes = $twoFactor->confirm($request->user(), $data['code']);

        if ($codes === null) {
            throw ValidationException::withMessages(['code' => __('auth.invalid_code')]);
        }

        return back()->with('recovery_codes', $codes)->with('success', __('Two-factor sign-in is on.'));
    }

    public function disableTwoFactor(Request $request, TwoFactor $twoFactor): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $twoFactor->disable($request->user());

        return back()->with('success', __('Two-factor sign-in is off.'));
    }
}
