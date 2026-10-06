<?php

namespace Tests\Feature;

use App\Actions\Schools\AddSchoolMember;
use App\Enums\SchoolRole;
use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Stage;
use App\Models\Subject;
use App\Models\Term;
use App\Support\Tenancy\CurrentSchool;
use App\Support\Tenancy\SchoolContextMismatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    /** Every tenant model must be filtered by the current school. */
    public static function tenantModels(): array
    {
        return [
            [Campus::class], [Stage::class], [GradeLevel::class], [AcademicYear::class],
            [Term::class], [Section::class], [Subject::class],
        ];
    }

    #[DataProvider('tenantModels')]
    public function test_tenant_models_only_see_the_current_school(string $model): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();
        $this->seedStructure($schoolA);
        $this->seedStructure($schoolB);

        $current = app(CurrentSchool::class);

        $idsA = $current->run($schoolA, fn () => $model::query()->pluck('school_id')->unique()->all());
        $idsB = $current->run($schoolB, fn () => $model::query()->pluck('school_id')->unique()->all());

        $this->assertSame([$schoolA->id], array_values($idsA));
        $this->assertSame([$schoolB->id], array_values($idsB));
    }

    public function test_queries_without_a_school_return_nothing(): void
    {
        $school = $this->createSchool();
        $this->seedStructure($school);

        $this->assertSame(0, Subject::query()->count());
        $this->assertSame(0, GradeLevel::query()->count());
    }

    public function test_new_rows_get_the_current_school(): void
    {
        $school = $this->createSchool();

        $subject = app(CurrentSchool::class)->run($school, fn () => Subject::query()->create(['code' => 'X', 'name_ar' => 'س']));

        $this->assertSame($school->id, $subject->school_id);
    }

    public function test_saving_without_a_school_is_refused(): void
    {
        $this->expectException(SchoolContextMismatch::class);

        Subject::query()->create(['code' => 'X', 'name_ar' => 'س']);
    }

    public function test_saving_for_another_school_is_refused(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        $this->expectException(SchoolContextMismatch::class);

        app(CurrentSchool::class)->run($schoolA, function () use ($schoolB) {
            $subject = new Subject(['code' => 'X', 'name_ar' => 'س']);
            $subject->school_id = $schoolB->id;
            $subject->save();
        });
    }

    public function test_api_cannot_read_another_schools_record_by_id(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();
        $subjectB = app(CurrentSchool::class)->run($schoolB, fn () => Subject::query()->create(['code' => 'B', 'name_ar' => 'ب']));

        Sanctum::actingAs($this->memberOf($schoolA, SchoolRole::SchoolAdmin));

        $this->getJson("/api/v1/subjects/{$subjectB->id}", $this->schoolHeader($schoolA))->assertNotFound();
        $this->putJson("/api/v1/subjects/{$subjectB->id}", ['name_ar' => 'x'], $this->schoolHeader($schoolA))->assertNotFound();
        $this->deleteJson("/api/v1/subjects/{$subjectB->id}", [], $this->schoolHeader($schoolA))->assertNotFound();
    }

    public function test_api_refuses_a_school_the_user_does_not_belong_to(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();

        Sanctum::actingAs($this->memberOf($schoolA, SchoolRole::SchoolAdmin));

        $this->getJson('/api/v1/subjects', $this->schoolHeader($schoolB))->assertForbidden();
        $this->getJson('/api/v1/subjects', [config('madrasa.school_header') => 'no-such-school'])->assertForbidden();
    }

    public function test_api_refuses_a_suspended_school(): void
    {
        $school = $this->createSchool();
        $user = $this->memberOf($school, SchoolRole::SchoolAdmin);
        $school->update(['status' => 'suspended']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/subjects', $this->schoolHeader($school))->assertForbidden();
    }

    public function test_api_cannot_link_records_to_another_schools_ids(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();
        [$yearB, $gradeB] = $this->seedStructure($schoolB);
        [$yearA, $gradeA] = $this->seedStructure($schoolA);

        Sanctum::actingAs($this->memberOf($schoolA, SchoolRole::SchoolAdmin));

        $this->postJson('/api/v1/sections', [
            'academic_year_id' => $yearB->id,
            'grade_level_id' => $gradeB->id,
            'name' => 'أ',
        ], $this->schoolHeader($schoolA))->assertUnprocessable()->assertJsonValidationErrors(['academic_year_id', 'grade_level_id']);

        $this->postJson('/api/v1/sections', [
            'academic_year_id' => $yearA->id,
            'grade_level_id' => $gradeA->id,
            'name' => 'ج',
        ], $this->schoolHeader($schoolA))->assertCreated();
    }

    public function test_a_single_membership_needs_no_header(): void
    {
        $school = $this->createSchool();
        Sanctum::actingAs($this->memberOf($school, SchoolRole::Teacher));

        $this->getJson('/api/v1/school')->assertOk()->assertJsonPath('data.slug', $school->slug);
    }

    public function test_several_memberships_require_the_header(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();
        $user = $this->memberOf($schoolA, SchoolRole::Teacher);
        app(AddSchoolMember::class)->handle($schoolB, $user, SchoolRole::Teacher);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/school')->assertStatus(400);
        $this->getJson('/api/v1/school', $this->schoolHeader($schoolB))->assertOk()->assertJsonPath('data.id', $schoolB->id);
    }

    /** @return array{0: AcademicYear, 1: GradeLevel} */
    private function seedStructure($school): array
    {
        return app(CurrentSchool::class)->run($school, function () {
            $year = AcademicYear::query()->create(['name' => '1448', 'starts_on' => '2026-08-23', 'ends_on' => '2027-06-10']);
            $year->terms()->create(['name_ar' => 'الأول', 'sequence' => 1, 'starts_on' => '2026-08-23', 'ends_on' => '2026-11-19']);
            $grade = GradeLevel::query()->first();
            Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'أ']);
            Subject::query()->create(['code' => 'MATH', 'name_ar' => 'الرياضيات']);

            return [$year, $grade];
        });
    }
}
