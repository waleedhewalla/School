<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates (or promotes) the SaaS operator account on a fresh install.
 * The account must turn on two-factor sign-in before the platform console
 * opens in production.
 */
#[Signature('madrasa:create-platform-admin {email} {--name=Platform admin}')]
#[Description('Create or promote a platform admin (asks for the password)')]
class CreatePlatformAdmin extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $password = (string) $this->secret('Password (at least 12 characters)');
            $check = Validator::make(['email' => $email, 'password' => $password], [
                'email' => ['required', 'email'],
                'password' => ['required', Password::min(12)],
            ]);
            if ($check->fails()) {
                $this->error($check->errors()->first());

                return self::FAILURE;
            }
            $user = User::query()->create(['name' => $this->option('name'), 'email' => $email, 'password' => Hash::make($password)]);
        }

        $user->forceFill(['is_platform_admin' => true, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        $this->info("{$email} is a platform admin. Sign in, turn on two-factor sign-in under Account, then open /platform.");

        return self::SUCCESS;
    }
}
