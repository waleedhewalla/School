<?php

namespace Tests\Feature;

use App\Enums\SchoolRole;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class StudentAdmissionTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    public function test_registrar_admits_a_student_with_guardians_and_enrollment(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        Sanctum::actingAs($this->memberOf($school, SchoolRole::Registrar));

        $response = $this->postJson('/api/v1/students', [
            'national_id' => $this->saudiId(1234567),
            'first_name_ar' => 'عبدالله',
            'father_name_ar' => 'خالد',
            'grandfather_name_ar' => 'سعد',
            'family_name_ar' => 'القحطاني',
            'name_en' => 'Abdullah Al-Qahtani',
            'gender' => 'male',
            'date_of_birth' => '2020-01-15',
            'guardians' => [
                ['name_ar' => 'خالد سعد القحطاني', 'national_id' => $this->saudiId(7654321), 'phone' => '+966500000001', 'relationship' => 'father', 'is_primary' => true],
                ['name_ar' => 'نورة القحطاني', 'relationship' => 'mother'],
            ],
            'enrollment' => ['academic_year_id' => $data['year']->id, 'grade_level_id' => $data['grade']->id, 'section_id' => $data['sectionB']->id],
        ], $this->schoolHeader($school))->assertCreated();

        $response->assertJsonPath('data.name_ar', 'عبدالله خالد سعد القحطاني')
            ->assertJsonPath('data.student_number', '000003')
            ->assertJsonCount(2, 'data.guardians')
            ->assertJsonPath('data.enrollments.0.section_id', $data['sectionB']->id);
    }

    public function test_siblings_share_a_family_through_a_guardian(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        Sanctum::actingAs($this->memberOf($school, SchoolRole::Registrar));

        $firstChild = $data['students'][0];
        $guardianId = $this->inSchool($school, fn () => $firstChild->guardians()->first()->id);

        $siblingFamily = $this->postJson('/api/v1/students', [
            'first_name_ar' => 'ريم',
            'family_name_ar' => 'العتيبي',
            'gender' => 'female',
            'guardians' => [['guardian_id' => $guardianId, 'relationship' => 'father']],
        ], $this->schoolHeader($school))->assertCreated()->json('data.family_id');

        $this->assertSame($firstChild->family_id, $siblingFamily);
    }

    public function test_invalid_national_id_is_rejected_in_arabic(): void
    {
        $school = $this->createSchool();
        Sanctum::actingAs($this->memberOf($school, SchoolRole::Registrar));

        $this->postJson('/api/v1/students?lang=ar', [
            'national_id' => '1234567890',
            'first_name_ar' => 'س', 'family_name_ar' => 'ص', 'gender' => 'male',
        ], $this->schoolHeader($school))
            ->assertUnprocessable()
            ->assertJsonPath('errors.national_id.0', 'يجب أن يكون رقم الهوية رقم هوية وطنية أو إقامة صحيحًا.');
    }

    public function test_full_section_is_refused(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        $this->inSchool($school, fn () => $data['sectionA']->update(['capacity' => 2]));
        Sanctum::actingAs($this->memberOf($school, SchoolRole::Registrar));

        $this->postJson('/api/v1/students', [
            'first_name_ar' => 'س', 'family_name_ar' => 'ص', 'gender' => 'male',
            'enrollment' => ['academic_year_id' => $data['year']->id, 'grade_level_id' => $data['grade']->id, 'section_id' => $data['sectionA']->id],
        ], $this->schoolHeader($school))->assertUnprocessable()->assertJsonValidationErrors('section_id');

        // The whole admission rolled back.
        $this->assertSame(2, $this->inSchool($school, fn () => Student::query()->count()));
    }

    public function test_cannot_use_a_guardian_from_another_school(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();
        $dataB = $this->seedSchoolData($schoolB);
        $guardianB = $this->inSchool($schoolB, fn () => $dataB['students'][0]->guardians()->first()->id);
        Sanctum::actingAs($this->memberOf($schoolA, SchoolRole::Registrar));

        $this->postJson('/api/v1/students', [
            'first_name_ar' => 'س', 'family_name_ar' => 'ص', 'gender' => 'male',
            'guardians' => [['guardian_id' => $guardianB, 'relationship' => 'father']],
        ], $this->schoolHeader($schoolA))->assertUnprocessable()->assertJsonValidationErrors('guardians.0.guardian_id');
    }

    public function test_search_and_section_filter(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        Sanctum::actingAs($this->memberOf($school, SchoolRole::Teacher));
        $headers = $this->schoolHeader($school);

        $this->getJson('/api/v1/students?search=سارة', $headers)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/students?section_id='.$data['sectionA']->id, $headers)->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/students?section_id='.$data['sectionB']->id, $headers)->assertJsonCount(0, 'data');
    }

    public function test_teacher_cannot_admit(): void
    {
        $school = $this->createSchool();
        Sanctum::actingAs($this->memberOf($school, SchoolRole::Teacher));

        $this->postJson('/api/v1/students', ['first_name_ar' => 'س', 'family_name_ar' => 'ص', 'gender' => 'male'], $this->schoolHeader($school))
            ->assertForbidden();
    }

    public function test_transfer_between_sections_and_withdrawal(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        Sanctum::actingAs($this->memberOf($school, SchoolRole::Registrar));
        $headers = $this->schoolHeader($school);
        $enrollmentId = $this->inSchool($school, fn () => $data['students'][0]->enrollments()->first()->id);

        $this->patchJson("/api/v1/enrollments/{$enrollmentId}", ['section_id' => $data['sectionB']->id], $headers)
            ->assertOk()->assertJsonPath('data.section_id', $data['sectionB']->id);

        $this->patchJson("/api/v1/enrollments/{$enrollmentId}", ['status' => 'withdrawn', 'left_on' => '2026-10-01'], $headers)
            ->assertOk()->assertJsonPath('data.status', 'withdrawn');

        $this->patchJson("/api/v1/enrollments/{$enrollmentId}", ['section_id' => $data['sectionA']->id], $headers)->assertUnprocessable();
    }
}
