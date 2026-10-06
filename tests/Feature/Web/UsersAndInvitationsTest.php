<?php

namespace Tests\Feature\Web;

use App\Enums\Permission;
use App\Enums\SchoolRole;
use App\Mail\InvitationMail;
use App\Models\Membership;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class UsersAndInvitationsTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    private function inviteAndGetUrl($school, array $payload): string
    {
        Mail::fake();
        $this->post('/users/invitations', $payload)->assertSessionHas('success');

        $url = null;
        Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use (&$url, $payload) {
            $url = $mail->url;

            return $mail->hasTo($payload['email']);
        });

        return parse_url($url, PHP_URL_PATH);
    }

    public function test_new_person_accepts_an_invitation(): void
    {
        $school = $this->createSchool();
        $this->actingAs($this->memberOf($school, SchoolRole::SchoolAdmin));
        $path = $this->inviteAndGetUrl($school, ['name' => 'أ. منى', 'email' => 'mona@example.com', 'roles' => ['teacher', 'registrar']]);

        $this->get('/users')->assertInertia(fn (Assert $page) => $page->has('invitations', 1));
        $this->post('/logout');

        $this->get($path)->assertInertia(fn (Assert $page) => $page
            ->component('Auth/AcceptInvitation')
            ->where('hasAccount', false)
            ->where('email', 'mona@example.com'));

        $this->post($path, ['name' => 'منى الشهري', 'password' => 'mona-pass-1', 'password_confirmation' => 'mona-pass-1'])->assertRedirect('/dashboard');

        $mona = User::query()->where('email', 'mona@example.com')->sole();
        $this->assertAuthenticatedAs($mona);
        app(CurrentSchool::class)->run($school, function () use ($mona) {
            $this->assertTrue($mona->can(Permission::StudentsManage));
            $this->assertTrue($mona->can(Permission::AttendanceRecord));
        });

        // The link can't be used twice.
        $this->post('/logout');
        $this->get($path)->assertNotFound();
    }

    public function test_existing_account_confirms_its_password(): void
    {
        $school = $this->createSchool();
        User::factory()->create(['email' => 'known@example.com']);
        $this->actingAs($this->memberOf($school, SchoolRole::SchoolAdmin));
        $path = $this->inviteAndGetUrl($school, ['name' => 'معروف', 'email' => 'known@example.com', 'roles' => ['teacher']]);
        $this->post('/logout');

        $this->get($path)->assertInertia(fn (Assert $page) => $page->where('hasAccount', true));
        $this->post($path, ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post($path, ['password' => 'password'])->assertRedirect('/dashboard');
    }

    public function test_expired_invitations_and_permissions(): void
    {
        $school = $this->createSchool();
        $this->actingAs($this->memberOf($school, SchoolRole::SchoolAdmin));
        $path = $this->inviteAndGetUrl($school, ['name' => 'س', 'email' => 's@example.com', 'roles' => ['teacher']]);

        $this->travel(8)->days();
        $this->post('/logout');
        $this->get($path)->assertNotFound();

        $this->actingAs($this->memberOf($school, SchoolRole::Principal))
            ->post('/users/invitations', ['name' => 'x', 'email' => 'x@example.com', 'roles' => ['teacher']])
            ->assertForbidden();
    }

    public function test_admin_changes_roles_and_deactivates_but_not_themselves(): void
    {
        $school = $this->createSchool();
        $admin = $this->memberOf($school, SchoolRole::SchoolAdmin);
        $teacher = $this->memberOf($school, SchoolRole::Teacher);
        $this->actingAs($admin);

        $this->patch("/users/{$teacher->id}", ['roles' => ['teacher', 'registrar']])->assertSessionHasNoErrors();
        app(CurrentSchool::class)->run($school, function () use ($teacher) {
            $teacher->unsetRelation('roles')->unsetRelation('permissions');
            $this->assertTrue($teacher->can(Permission::StudentsManage));
        });

        $this->patch("/users/{$admin->id}", ['roles' => ['teacher']])->assertSessionHasErrors('roles');
        $this->patch("/users/{$teacher->id}", ['status' => 'inactive'])->assertSessionHasNoErrors();
        $this->assertSame('inactive', Membership::query()->where('user_id', $teacher->id)->value('status'));

        // A deactivated member can no longer enter the school.
        $this->actingAs($teacher)->get('/dashboard')->assertRedirect('/schools');
    }
}
