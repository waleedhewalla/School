<?php

namespace Tests\Feature;

use App\Enums\SchoolRole;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    public function test_promote_and_repeat_keep_history(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        [$ahmad, $sara] = $data['students'];

        [$nextYear, $grade2Section] = $this->inSchool($school, function () use ($data) {
            $year = AcademicYear::query()->create(['name' => '1449', 'starts_on' => '2027-08-22', 'ends_on' => '2028-06-08']);
            $grade2 = GradeLevel::query()->where('stage_id', $data['grade']->stage_id)->where('sequence', 2)->first();

            return [$year, Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade2->id, 'name' => 'أ'])];
        });

        Sanctum::actingAs($this->memberOf($school, SchoolRole::Registrar));

        $this->postJson('/api/v1/promotions', [
            'from_academic_year_id' => $data['year']->id,
            'to_academic_year_id' => $nextYear->id,
            'decisions' => [
                ['student_id' => $ahmad->id, 'outcome' => 'promoted', 'section_id' => $grade2Section->id],
                ['student_id' => $sara->id, 'outcome' => 'repeated'],
            ],
        ], $this->schoolHeader($school))->assertOk()->assertJsonCount(2, 'data');

        $this->inSchool($school, function () use ($ahmad, $sara, $data, $grade2Section) {
            $this->assertSame(['promoted', 'active'], $ahmad->enrollments()->orderBy('id')->get()->map(fn ($e) => $e->status->value)->all());
            $this->assertSame($grade2Section->id, $ahmad->enrollments()->latest('id')->first()->section_id);
            $this->assertSame($data['grade']->id, $sara->enrollments()->latest('id')->first()->grade_level_id);
            $this->assertSame(4, Enrollment::query()->count());
        });
    }

    public function test_grade_12_graduates_and_cannot_be_promoted(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        $student = $data['students'][0];

        $nextYear = $this->inSchool($school, function () use ($student) {
            $grade12 = GradeLevel::query()->whereHas('stage', fn ($q) => $q->where('code', 'secondary'))->where('sequence', 3)->first();
            $student->enrollments()->update(['grade_level_id' => $grade12->id, 'section_id' => null]);

            return AcademicYear::query()->create(['name' => '1449', 'starts_on' => '2027-08-22', 'ends_on' => '2028-06-08']);
        });

        Sanctum::actingAs($this->memberOf($school, SchoolRole::Registrar));
        $payload = fn ($outcome) => [
            'from_academic_year_id' => $data['year']->id,
            'to_academic_year_id' => $nextYear->id,
            'decisions' => [['student_id' => $student->id, 'outcome' => $outcome]],
        ];

        $this->postJson('/api/v1/promotions', $payload('promoted'), $this->schoolHeader($school))
            ->assertUnprocessable()->assertJsonValidationErrors('decisions.0.outcome');

        $this->postJson('/api/v1/promotions', $payload('graduated'), $this->schoolHeader($school))->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame('graduated', $this->inSchool($school, fn () => $student->fresh()->status));
    }
}
