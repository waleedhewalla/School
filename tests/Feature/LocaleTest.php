<?php

namespace Tests\Feature;

use App\Enums\SchoolRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    public function test_web_defaults_to_arabic_right_to_left(): void
    {
        $this->withServerVariables(['HTTP_ACCEPT_LANGUAGE' => null]);
        $this->get('/')->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('نظام إدارة المدارس');
    }

    public function test_english_is_left_to_right(): void
    {
        $this->get('/?lang=en')->assertOk()->assertSee('<html lang="en" dir="ltr">', false);
    }

    public function test_browser_language_is_used_when_nothing_else_is_set(): void
    {
        $this->get('/', ['Accept-Language' => 'en-GB,en;q=0.9'])->assertSee('dir="ltr"', false);
        $this->get('/', ['Accept-Language' => 'ar-SA,ar;q=0.9'])->assertSee('dir="rtl"', false);
    }

    public function test_language_switch_is_remembered(): void
    {
        $this->get('/locale/en')->assertRedirect();
        $this->get('/')->assertSee('dir="ltr"', false);

        $this->get('/locale/fr')->assertNotFound();
    }

    public function test_api_uses_the_users_language_over_the_schools(): void
    {
        $school = $this->createSchool();
        $user = $this->memberOf($school, SchoolRole::Teacher);
        $user->update(['locale' => 'en']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/school', $this->schoolHeader($school))
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('data.name', 'Test School');
    }

    public function test_api_falls_back_to_the_schools_language(): void
    {
        $school = $this->createSchool(attributes: ['default_locale' => 'ar']);
        Sanctum::actingAs($this->memberOf($school, SchoolRole::Teacher));

        $this->getJson('/api/v1/school', $this->schoolHeader($school) + ['Accept-Language' => 'en'])
            ->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('data.name', 'مدرسة اختبار');
    }

    public function test_tenancy_errors_are_translated(): void
    {
        $school = $this->createSchool();
        Sanctum::actingAs(User::factory()->create(['locale' => 'ar']));

        $this->getJson('/api/v1/subjects?lang=ar', $this->schoolHeader($school))
            ->assertForbidden()
            ->assertJsonPath('message', 'ليس لديك صلاحية الدخول إلى هذه المدرسة.');
    }
}
