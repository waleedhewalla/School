<?php

namespace Tests\Feature;

use App\Actions\Attendance\RecordAttendance;
use App\Enums\Permission;
use App\Enums\SchoolRole;
use App\Events\StudentsMarkedAbsent;
use App\Models\Invitation;
use App\Models\StaffMember;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Support\Auth\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

/** Regression tests for the pre-pilot security review. */
class SecurityHardeningTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    private function withTwoFactor(User $user): User
    {
        $twoFactor = app(TwoFactor::class);
        $twoFactor->begin($user);
        $twoFactor->confirm($user, $twoFactor->currentCode($user));

        return $user->fresh();
    }

    public function test_api_sign_in_is_throttled_per_account_and_per_code(): void
    {
        $user = $this->withTwoFactor(User::factory()->create(['email' => 'a@example.com']));
        $payload = ['email' => 'a@example.com', 'device_name' => 'x'];

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/auth/token', $payload + ['password' => 'wrong'])->assertJsonValidationErrors('email');
        }
        $this->postJson('/api/v1/auth/token', $payload + ['password' => 'password'])
            ->assertJsonPath('errors.email.0', fn ($m) => str_contains($m, '60') || str_contains($m, 'seconds') || str_contains($m, 'ثانية'));

        $this->travel(2)->minutes();
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/auth/token', $payload + ['password' => 'password', 'code' => '000000'])->assertJsonValidationErrors('code');
        }
        $this->postJson('/api/v1/auth/token', $payload + ['password' => 'password', 'code' => app(TwoFactor::class)->currentCode($user)])
            ->assertJsonValidationErrors('code'); // still locked out
    }

    public function test_two_factor_cannot_be_reset_while_on(): void
    {
        $user = $this->withTwoFactor($this->memberOf($this->createSchool(), SchoolRole::Teacher));

        $this->actingAs($user)->post('/account/two-factor')->assertSessionHasErrors('code');
        $this->assertTrue(app(TwoFactor::class)->enabled($user->fresh()));
    }

    public function test_teachers_cannot_browse_guardians_or_see_national_ids(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $this->inSchool($school, fn () => $d['students'][0]->update(['national_id' => $this->saudiId(5)]));
        $headers = $this->schoolHeader($school);
        $student = $d['students'][0];

        Sanctum::actingAs($d['teacher']);
        $this->getJson('/api/v1/guardians', $headers)->assertForbidden();
        $this->getJson("/api/v1/students/{$student->id}", $headers)->assertOk()->assertJsonMissingPath('data.national_id');

        Sanctum::actingAs($this->memberOf($school, SchoolRole::Registrar));
        $this->getJson("/api/v1/students/{$student->id}", $headers)->assertJsonPath('data.national_id', $this->saudiId(5));
    }

    public function test_one_alert_per_mark_and_no_old_registers_for_teachers(): void
    {
        Event::fake([StudentsMarkedAbsent::class]);
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $sara = $d['students'][1];
        $mark = fn (string $code, string $date = '2026-09-02', ?User $by = null) => $this->inSchool($school, fn () => app(RecordAttendance::class)
            ->handle($d['sectionA'], Carbon::parse($date), 0, [['student_id' => $sara->id, 'code' => $code]], $by ?? $d['teacher']));

        $mark('A');
        $mark('P');
        $mark('A');
        $mark('L');
        Event::assertDispatchedTimes(StudentsMarkedAbsent::class, 1);

        $this->expectExceptionMessage('لا يمكن تعديل سجل حضور أقدم من 7 أيام');
        app()->setLocale('ar');
        $mark('A', '2026-08-25');
    }

    public function test_registrars_may_correct_old_registers(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $registrar = $this->memberOf($school, SchoolRole::Registrar);

        $records = $this->inSchool($school, function () use ($d, $registrar) {
            $registrar->unsetRelation('roles');

            return app(RecordAttendance::class)->handle($d['sectionA'], Carbon::parse('2026-08-25'), 0, [['student_id' => $d['students'][0]->id, 'code' => 'E']], $registrar);
        });

        $this->assertCount(1, $records);
    }

    public function test_password_change_signs_out_other_sessions_and_tokens(): void
    {
        $user = $this->memberOf($this->createSchool(), SchoolRole::Teacher);
        $user->createToken('phone');
        DB::table('sessions')->insert(['id' => 'other', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($user)->put('/account/password', ['current_password' => 'password', 'password' => 'another-pass-1', 'password_confirmation' => 'another-pass-1'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertFalse(DB::table('sessions')->where('id', 'other')->exists());
    }

    public function test_invitation_for_a_two_factor_account_goes_through_the_code_step(): void
    {
        $school = $this->createSchool();
        $user = $this->withTwoFactor(User::factory()->create(['email' => 'tf@example.com']));
        [, $token] = $this->inSchool($school, fn () => Invitation::issue(['email' => 'tf@example.com', 'name' => 'x', 'roles' => ['teacher']]));

        $this->post("/invitations/{$token}", ['password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => app(TwoFactor::class)->currentCode($user)])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_remarks_need_an_active_homeroom_teacher_and_a_matching_term(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $term = $this->inSchool($school, fn () => Term::query()->first());
        $this->inSchool($school, fn () => TeachingAssignment::query()->update(['is_homeroom' => true]));
        $url = "/report-cards/{$d['sectionA']->id}/{$term->id}/comments";
        $this->actingAs($d['teacher']);

        $this->post($url, ['student_id' => $d['students'][0]->id, 'comment' => 'جيد'])->assertSessionHas('success');

        $this->inSchool($school, fn () => StaffMember::query()->update(['status' => 'inactive']));
        $this->post($url, ['student_id' => $d['students'][0]->id, 'comment' => 'جيد'])->assertForbidden();
    }

    public function test_import_rejects_oversized_files_and_cleans_up(): void
    {
        Storage::fake('local');
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $this->actingAs($this->memberOf($school, SchoolRole::Registrar));

        $path = tempnam(sys_get_temp_dir(), 'big').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['اسم الطالب', 'الجنس', 'الصف']));
        foreach (range(1, 2001) as $i) {
            $writer->addRow(Row::fromValues(["طالب {$i} العتيبي", 'ذكر', 'الصف الأول الابتدائي']));
        }
        $writer->close();

        $this->post('/students/import/preview', ['file' => new UploadedFile($path, 'big.xlsx', null, null, true), 'academic_year_id' => $d['year']->id])
            ->assertSessionHasErrors('file');
        $this->assertSame([], Storage::disk('local')->allFiles('imports'));

        Storage::disk('local')->put('imports/1/old.xlsx', 'x');
        Storage::disk('local')->put('imports/1/new.xlsx', 'x');
        touch(Storage::disk('local')->path('imports/1/old.xlsx'), now()->subDays(2)->getTimestamp());
        touch(Storage::disk('local')->path('imports/1/new.xlsx'), now()->getTimestamp());
        $this->artisan('madrasa:prune-imports')->assertSuccessful();
        Storage::disk('local')->assertMissing('imports/1/old.xlsx');
        Storage::disk('local')->assertExists('imports/1/new.xlsx');
    }

    public function test_no_granting_roles_beyond_your_own_access(): void
    {
        $school = $this->createSchool();
        $teacher = $this->memberOf($school, SchoolRole::Teacher);
        $deputy = $this->memberOf($school, SchoolRole::Teacher);
        $this->inSchool($school, function () use ($deputy) {
            $role = Role::query()->create(['name' => 'deputy', 'guard_name' => 'web']);
            $role->givePermissionTo([Permission::MembersManage]);
            $deputy->assignRole('deputy');
        });

        $this->actingAs($deputy)->patch("/users/{$teacher->id}", ['roles' => ['school_admin']])->assertSessionHasErrors('roles');
        $this->post('/users/invitations', ['name' => 'x', 'email' => 'x@example.com', 'roles' => ['school_admin']])->assertSessionHasErrors('roles');
    }

    public function test_platform_admins_need_two_factor_outside_development(): void
    {
        $school = $this->createSchool();
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->assertTrue($admin->canEnterSchool($school));

        $this->app['env'] = 'production';
        $this->assertFalse($admin->canEnterSchool($school));
        $this->assertTrue($this->withTwoFactor($admin)->canEnterSchool($school));
    }
}
