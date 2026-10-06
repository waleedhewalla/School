<?php

namespace Tests\Feature;

use App\Enums\SchoolRole;
use App\Events\StudentsMarkedAbsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    public function test_assigned_teacher_takes_the_register(): void
    {
        Event::fake([StudentsMarkedAbsent::class]);
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        [$ahmad, $sara] = $data['students'];
        Sanctum::actingAs($data['teacher']);
        $url = "/api/v1/sections/{$data['sectionA']->id}/attendance?date=2026-09-02";
        $headers = $this->schoolHeader($school);

        $this->getJson($url, $headers)->assertOk()
            ->assertJsonPath('data.taken', false)
            ->assertJsonCount(2, 'data.students');

        $this->putJson($url, ['records' => [
            ['student_id' => $ahmad->id, 'code' => 'P'],
            ['student_id' => $sara->id, 'code' => 'A', 'note' => 'مريضة'],
        ]], $headers)->assertOk()->assertJsonCount(2, 'data');

        Event::assertDispatched(StudentsMarkedAbsent::class, fn ($event) => $event->records->pluck('student_id')->all() === [$sara->id]);

        $this->getJson($url, $headers)->assertJsonPath('data.taken', true)
            ->assertJsonFragment(['student_id' => $sara->id, 'code' => 'A', 'note' => 'مريضة']);
    }

    public function test_correcting_a_register_does_not_alert_twice(): void
    {
        Event::fake([StudentsMarkedAbsent::class]);
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        $sara = $data['students'][1];
        Sanctum::actingAs($data['teacher']);
        $url = "/api/v1/sections/{$data['sectionA']->id}/attendance?date=2026-09-02";

        $this->putJson($url, ['records' => [['student_id' => $sara->id, 'code' => 'A']]], $this->schoolHeader($school))->assertOk();
        $this->putJson($url, ['records' => [['student_id' => $sara->id, 'code' => 'A', 'note' => 'تصحيح']]], $this->schoolHeader($school))->assertOk();

        Event::assertDispatchedTimes(StudentsMarkedAbsent::class, 1);
    }

    public function test_teacher_cannot_take_register_for_a_section_they_do_not_teach(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        Sanctum::actingAs($data['teacher']);

        $this->putJson("/api/v1/sections/{$data['sectionB']->id}/attendance?date=2026-09-02", [
            'records' => [['student_id' => $data['students'][0]->id, 'code' => 'P']],
        ], $this->schoolHeader($school))->assertForbidden();
    }

    public function test_registrar_may_take_any_register_but_only_for_enrolled_students(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        Sanctum::actingAs($this->memberOf($school, SchoolRole::Registrar));

        $this->putJson("/api/v1/sections/{$data['sectionB']->id}/attendance?date=2026-09-02", [
            'records' => [['student_id' => $data['students'][0]->id, 'code' => 'P']],
        ], $this->schoolHeader($school))->assertUnprocessable()->assertJsonValidationErrors('records.0.student_id');
    }

    public function test_per_period_registers_are_separate_and_codes_are_checked(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        $ahmad = $data['students'][0];
        Sanctum::actingAs($data['teacher']);
        $base = "/api/v1/sections/{$data['sectionA']->id}/attendance?date=2026-09-02";
        $headers = $this->schoolHeader($school);

        $this->putJson($base.'&period=3', ['records' => [['student_id' => $ahmad->id, 'code' => 'L']]], $headers)->assertOk();
        $this->getJson($base, $headers)->assertJsonPath('data.taken', false);
        $this->getJson($base.'&period=3', $headers)->assertJsonFragment(['student_id' => $ahmad->id, 'code' => 'L']);

        $this->putJson($base, ['records' => [['student_id' => $ahmad->id, 'code' => 'Z']]], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('records.0.code');
        $this->putJson('/api/v1/sections/'.$data['sectionA']->id.'/attendance?date=2999-01-01', ['records' => [['student_id' => $ahmad->id, 'code' => 'P']]], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('date');
    }

    public function test_codes_are_bilingual(): void
    {
        $school = $this->createSchool();
        Sanctum::actingAs($this->memberOf($school, SchoolRole::Teacher));

        $this->getJson('/api/v1/attendance-codes?lang=ar', $this->schoolHeader($school))
            ->assertOk()->assertJsonPath('data.1.name', 'غائب');
        $this->getJson('/api/v1/attendance-codes?lang=en', $this->schoolHeader($school))
            ->assertJsonPath('data.1.name', 'Absent');
    }
}
