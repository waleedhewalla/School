<?php

namespace Tests\Feature;

use App\Actions\Schools\AddSchoolMember;
use App\Enums\Permission;
use App\Enums\SchoolRole;
use App\Models\GradeLevel;
use App\Models\Stage;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class SchoolRolesTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    public function test_creating_a_school_provisions_roles_campus_and_saudi_stages(): void
    {
        $admin = User::factory()->create();
        $school = $this->createSchool($admin);

        $this->assertSame(
            collect(SchoolRole::cases())->map->value->sort()->values()->all(),
            Role::query()->where('school_id', $school->id)->pluck('name')->sort()->values()->all(),
        );

        app(CurrentSchool::class)->run($school, function () {
            $this->assertSame(['kg', 'primary', 'intermediate', 'secondary'], Stage::query()->orderBy('sequence')->pluck('code')->all());
            $this->assertSame(15, GradeLevel::query()->count());
        });

        $this->assertTrue($admin->canEnterSchool($school));
    }

    public function test_roles_are_per_school(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();
        $user = $this->memberOf($schoolA, SchoolRole::SchoolAdmin);
        app(AddSchoolMember::class)->handle($schoolB, $user, SchoolRole::Teacher);

        $current = app(CurrentSchool::class);

        $current->run($schoolA, function () use ($user) {
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $this->assertTrue($user->can(Permission::AcademicStructureManage));
        });

        $current->run($schoolB, function () use ($user) {
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $this->assertFalse($user->can(Permission::AcademicStructureManage));
            $this->assertTrue($user->can(Permission::AttendanceRecord));
        });
    }

    public function test_teacher_can_read_but_not_change_academic_structure(): void
    {
        $school = $this->createSchool();
        Sanctum::actingAs($this->memberOf($school, SchoolRole::Teacher));

        $this->getJson('/api/v1/subjects', $this->schoolHeader($school))->assertOk();
        $this->postJson('/api/v1/subjects', ['code' => 'MATH', 'name_ar' => 'الرياضيات'], $this->schoolHeader($school))->assertForbidden();
    }

    public function test_guardian_cannot_read_staff_endpoints(): void
    {
        $school = $this->createSchool();
        Sanctum::actingAs($this->memberOf($school, SchoolRole::Guardian));

        $this->getJson('/api/v1/subjects', $this->schoolHeader($school))->assertForbidden();
    }

    public function test_school_admin_manages_academic_structure(): void
    {
        $school = $this->createSchool();
        Sanctum::actingAs($this->memberOf($school, SchoolRole::SchoolAdmin));
        $headers = $this->schoolHeader($school);

        $year = $this->postJson('/api/v1/academic-years', [
            'name' => '1448',
            'starts_on' => '2026-08-23',
            'ends_on' => '2027-06-10',
            'is_current' => true,
            'terms' => [
                ['name_ar' => 'الفصل الأول', 'name_en' => 'Term 1', 'starts_on' => '2026-08-23', 'ends_on' => '2026-11-19'],
                ['name_ar' => 'الفصل الثاني', 'name_en' => 'Term 2', 'starts_on' => '2026-11-29', 'ends_on' => '2027-03-04'],
            ],
        ], $headers)->assertCreated()
            ->assertJsonPath('data.is_current', true)
            ->assertJsonCount(2, 'data.terms')
            ->json('data');

        $gradeId = $this->getJson('/api/v1/grade-levels', $headers)->assertOk()->json('data.1.grade_levels.0.id');

        $this->postJson('/api/v1/sections', [
            'academic_year_id' => $year['id'],
            'grade_level_id' => $gradeId,
            'name' => 'أ',
            'capacity' => 30,
        ], $headers)->assertCreated();

        // Same name in the same grade and year is rejected.
        $this->postJson('/api/v1/sections', [
            'academic_year_id' => $year['id'],
            'grade_level_id' => $gradeId,
            'name' => 'أ',
        ], $headers)->assertUnprocessable();

        $subjectId = $this->postJson('/api/v1/subjects', ['code' => 'MATH', 'name_ar' => 'الرياضيات', 'name_en' => 'Mathematics'], $headers)
            ->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/subjects/{$subjectId}", ['name_en' => 'Maths'], $headers)->assertOk()->assertJsonPath('data.name_en', 'Maths');
        $this->deleteJson("/api/v1/subjects/{$subjectId}", [], $headers)->assertNoContent();
    }

    public function test_only_one_current_academic_year(): void
    {
        $school = $this->createSchool();
        Sanctum::actingAs($this->memberOf($school, SchoolRole::SchoolAdmin));
        $headers = $this->schoolHeader($school);

        $first = $this->postJson('/api/v1/academic-years', ['name' => '1447', 'starts_on' => '2025-08-24', 'ends_on' => '2026-06-11', 'is_current' => true], $headers)->json('data.id');
        $this->postJson('/api/v1/academic-years', ['name' => '1448', 'starts_on' => '2026-08-23', 'ends_on' => '2027-06-10', 'is_current' => true], $headers)->assertCreated();

        $this->getJson("/api/v1/academic-years/{$first}", $headers)->assertJsonPath('data.is_current', false);
    }

    public function test_only_platform_admins_create_schools(): void
    {
        $admin = User::factory()->create(['email' => 'owner@example.com']);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/schools', ['slug' => 'x', 'name_ar' => 'س', 'admin_email' => $admin->email])->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['is_platform_admin' => true]));
        $this->postJson('/api/v1/schools', ['slug' => 'al-noor', 'name_ar' => 'مدارس النور', 'admin_email' => $admin->email])
            ->assertCreated()->assertJsonPath('data.slug', 'al-noor');

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/me')->assertOk()
            ->assertJsonPath('data.schools.0.slug', 'al-noor')
            ->assertJsonPath('data.schools.0.roles', [SchoolRole::SchoolAdmin->value]);
    }
}
