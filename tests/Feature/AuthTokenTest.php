<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_issues_and_revokes_tokens(): void
    {
        User::factory()->create(['email' => 'a@example.com']);

        $token = $this->postJson('/api/v1/auth/token', ['email' => 'a@example.com', 'password' => 'password', 'device_name' => 'phone'])
            ->assertCreated()->json('token');

        $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])->assertOk()->assertJsonPath('data.email', 'a@example.com');
        $this->deleteJson('/api/v1/auth/token', [], ['Authorization' => "Bearer {$token}"])->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
    }

    public function test_wrong_password_is_rejected_in_the_request_language(): void
    {
        User::factory()->create(['email' => 'a@example.com']);

        $this->postJson('/api/v1/auth/token?lang=ar', ['email' => 'a@example.com', 'password' => 'nope', 'device_name' => 'phone'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'بيانات الدخول غير صحيحة.');
    }
}
