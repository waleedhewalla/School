<?php

namespace Tests\Feature;

use App\Enums\SchoolRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class GuardianPortalTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    public function test_guardian_sees_only_their_children(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        [$ahmad, $sara] = $data['students'];
        $parent = $this->memberOf($school, SchoolRole::Guardian);

        // Link the parent login to Ahmad's guardian record via the API.
        Sanctum::actingAs($this->memberOf($school, SchoolRole::Registrar));
        $guardianId = $this->inSchool($school, fn () => $ahmad->guardians()->first()->id);
        $this->postJson("/api/v1/guardians/{$guardianId}/user", ['user_id' => $parent->id], $this->schoolHeader($school))->assertOk();

        Sanctum::actingAs($parent);
        $headers = $this->schoolHeader($school);

        $this->getJson('/api/v1/my/children', $headers)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ahmad->id)
            ->assertJsonPath('data.0.current_enrollment.section.id', $data['sectionA']->id);

        $this->getJson("/api/v1/my/children/{$ahmad->id}/attendance", $headers)->assertOk()
            ->assertJsonPath('data.0.code', 'P');
        $this->getJson("/api/v1/my/children/{$sara->id}/attendance", $headers)->assertNotFound();

        $this->getJson("/api/v1/students/{$ahmad->id}", $headers)->assertOk();
        $this->getJson("/api/v1/students/{$sara->id}", $headers)->assertForbidden();
        $this->getJson('/api/v1/students', $headers)->assertForbidden();
    }

    public function test_guardian_login_must_belong_to_the_school(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();
        $data = $this->seedSchoolData($schoolA);
        $outsider = $this->memberOf($schoolB, SchoolRole::Guardian);

        Sanctum::actingAs($this->memberOf($schoolA, SchoolRole::Registrar));
        $guardianId = $this->inSchool($schoolA, fn () => $data['students'][0]->guardians()->first()->id);

        $this->postJson("/api/v1/guardians/{$guardianId}/user", ['user_id' => $outsider->id], $this->schoolHeader($schoolA))
            ->assertUnprocessable();
    }
}
