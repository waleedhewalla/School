<?php

namespace Tests\Feature\Web;

use App\Enums\SchoolRole;
use App\Models\User;
use App\Support\Auth\TwoFactor;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    private function userWithTwoFactor(): array
    {
        $school = $this->createSchool();
        $user = $this->memberOf($school, SchoolRole::Teacher);
        $twoFactor = app(TwoFactor::class);

        $this->actingAs($user)->post('/account/two-factor');
        $this->get('/account')->assertInertia(fn (Assert $page) => $page->where('twoFactor.pending', true)->has('twoFactor.qr'));

        $this->post('/account/two-factor/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/account/two-factor/confirm', ['code' => $twoFactor->currentCode($user->fresh())])->assertSessionHas('recovery_codes');
        $codes = session('recovery_codes');
        $this->post('/logout');

        return [$user->fresh(), $codes, $twoFactor];
    }

    public function test_two_factor_sign_in(): void
    {
        [$user, , $twoFactor] = $this->userWithTwoFactor();
        $this->assertCount(8, json_decode(decrypt($user->two_factor_recovery_codes)));

        // Password alone only reaches the code step.
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => $twoFactor->currentCode($user)])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_recovery_codes_work_once(): void
    {
        [$user, $codes] = $this->userWithTwoFactor();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => strtoupper($codes[0])])->assertRedirect('/dashboard');
        $this->post('/logout');

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => $codes[0]])->assertSessionHasErrors('code');
    }

    public function test_api_tokens_need_the_code_too(): void
    {
        [$user, , $twoFactor] = $this->userWithTwoFactor();
        $payload = ['email' => $user->email, 'password' => 'password', 'device_name' => 'phone'];

        $this->postJson('/api/v1/auth/token', $payload)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/auth/token', $payload + ['code' => $twoFactor->currentCode($user)])->assertCreated();
    }

    public function test_turning_off_needs_the_password(): void
    {
        [$user, , $twoFactor] = $this->userWithTwoFactor();
        $this->actingAs($user);

        $this->delete('/account/two-factor', ['current_password' => 'nope'])->assertSessionHasErrors('current_password');
        $this->delete('/account/two-factor', ['current_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertFalse($twoFactor->enabled($user->fresh()));
    }

    public function test_change_password_and_language(): void
    {
        $user = $this->memberOf($this->createSchool(), SchoolRole::Teacher);
        $user->update(['locale' => 'ar']);
        $this->actingAs($user);

        $this->put('/account/password', ['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertSessionHasErrors('current_password');
        $this->put('/account/password', ['current_password' => 'password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertSessionHasNoErrors();
        $this->put('/account', ['name' => 'اسم جديد', 'locale' => 'en'])->assertSessionHasNoErrors();

        $this->assertSame(['اسم جديد', 'en'], [$user->fresh()->name, $user->fresh()->locale]);
    }

    public function test_password_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);

        // Unknown and known emails get the same answer.
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('success');
        $this->post('/forgot-password', ['email' => 'reset@example.com'])->assertSessionHas('success');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->get("/reset-password/{$token}?email=reset@example.com")->assertInertia(fn (Assert $page) => $page->component('Auth/ResetPassword'));
        $this->post('/reset-password', ['token' => $token, 'email' => 'reset@example.com', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertRedirect('/login');

        $this->post('/login', ['email' => 'reset@example.com', 'password' => 'brand-new-pass'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }
}
