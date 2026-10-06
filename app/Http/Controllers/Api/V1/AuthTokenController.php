<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\TwoFactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthTokenController extends Controller
{
    public function store(Request $request, TwoFactor $twoFactor): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
        ]);

        // Same limits as the web sign-in: per account (not only per IP), and
        // a separate per-user limit on two-factor codes.
        $passwordKey = 'api-login|'.Str::lower($credentials['email']).'|'.$request->ip();
        $this->throttle($passwordKey, 'email');

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($passwordKey);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        RateLimiter::clear($passwordKey);

        if ($twoFactor->enabled($user)) {
            $codeKey = 'two-factor|'.$user->id;
            $this->throttle($codeKey, 'code');

            if (! $twoFactor->verify($user, (string) ($credentials['code'] ?? ''))) {
                RateLimiter::hit($codeKey);

                throw ValidationException::withMessages(['code' => __('auth.invalid_code')]);
            }
            RateLimiter::clear($codeKey);
        }

        return response()->json([
            'token' => $user->createToken($credentials['device_name'])->plainTextToken,
            'token_type' => 'Bearer',
        ], 201);
    }

    private function throttle(string $key, string $field): void
    {
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([$field => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)])]);
        }
    }

    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
