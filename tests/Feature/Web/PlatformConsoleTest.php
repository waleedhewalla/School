<?php

namespace Tests\Feature\Web;

use App\Enums\SchoolRole;
use App\Mail\InvitationMail;
use App\Models\GradeLevel;
use App\Models\Invitation;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class PlatformConsoleTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    private function platformAdmin(): User
    {
        return User::factory()->create(['is_platform_admin' => true]);
    }

    public function test_platform_admin_sees_every_school_with_counts(): void
    {
        $a = $this->createSchool();
        $this->seedSchoolData($a);
        $this->createSchool();
        $this->actingAs($this->platformAdmin());

        $this->get('/dashboard')->assertRedirect('/platform');
        $this->get('/platform')->assertInertia(fn (Assert $page) => $page->component('Platform/Index')
            ->where('totals.schools', 2)->where('totals.students', 2)
            ->where('schools', fn ($schools) => collect($schools)->firstWhere('id', $a->id)['applications'] === 1));
    }

    public function test_onboarding_a_school_invites_a_new_admin_or_links_an_existing_user(): void
    {
        Mail::fake();
        $this->actingAs($this->platformAdmin());

        $this->post('/platform/schools', [
            'slug' => 'al-riyada', 'name_ar' => 'مدارس الريادة', 'default_locale' => 'ar', 'date_display' => 'both',
            'admin_name' => 'سعد', 'admin_email' => 'saad@example.com',
        ])->assertSessionHasNoErrors();

        $school = School::query()->where('slug', 'al-riyada')->firstOrFail();
        Mail::assertSent(InvitationMail::class, fn ($mail) => $mail->hasTo('saad@example.com'));
        $this->assertSame(['school_admin'], $this->inSchool($school, fn () => Invitation::query()->sole()->roles));
        $this->assertSame(15, $this->inSchool($school, fn () => GradeLevel::query()->count()));

        $existing = User::factory()->create(['email' => 'huda@example.com']);
        $this->post('/platform/schools', [
            'slug' => 'al-noor', 'name_ar' => 'مدارس النور', 'default_locale' => 'ar', 'date_display' => 'hijri',
            'admin_name' => 'هدى', 'admin_email' => 'huda@example.com',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($existing->canEnterSchool(School::query()->where('slug', 'al-noor')->firstOrFail()));

        $this->post('/platform/schools', ['slug' => 'al-noor', 'name_ar' => 'x', 'default_locale' => 'ar', 'date_display' => 'both', 'admin_name' => 'x', 'admin_email' => 'x@example.com'])
            ->assertSessionHasErrors('slug');
        $this->post('/platform/schools', ['slug' => 'Bad Slug', 'name_ar' => 'x', 'default_locale' => 'ar', 'date_display' => 'both', 'admin_name' => 'x', 'admin_email' => 'x@example.com'])
            ->assertSessionHasErrors('slug');
    }

    public function test_suspend_reactivate_and_enter(): void
    {
        $school = $this->createSchool();
        $member = $this->memberOf($school, SchoolRole::SchoolAdmin);
        $this->actingAs($this->platformAdmin());

        $this->patch("/platform/schools/{$school->id}", ['status' => 'suspended'])->assertSessionHasNoErrors();
        $this->assertFalse($member->canEnterSchool($school->refresh()));
        $this->post("/platform/schools/{$school->id}/enter")->assertForbidden();

        $this->patch("/platform/schools/{$school->id}", ['status' => 'active']);
        $this->post("/platform/schools/{$school->id}/enter")->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();
    }

    public function test_school_admins_cannot_reach_the_console(): void
    {
        $school = $this->createSchool();
        $this->actingAs($this->memberOf($school, SchoolRole::SchoolAdmin));

        $this->get('/platform')->assertForbidden();
        $this->post('/platform/schools', [])->assertForbidden();
        $this->patch("/platform/schools/{$school->id}", ['status' => 'suspended'])->assertForbidden();
    }
}
