<?php

namespace Tests\Feature;

use App\Enums\SchoolRole;
use App\Models\BehaviourCategory;
use App\Models\BehaviourIncident;
use App\Models\Homework;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class FamilyApiTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    public function test_guardian_reads_own_child_only_and_sends_an_excuse(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        [$ahmad, $sara] = $d['students'];
        $parent = $this->memberOf($school, SchoolRole::Guardian);
        $this->inSchool($school, function () use ($ahmad, $parent, $d) {
            $ahmad->guardians()->first()->forceFill(['user_id' => $parent->id])->save();
            Homework::query()->create(['section_id' => $d['sectionA']->id, 'subject_id' => $d['subject']->id, 'title' => 'واجب', 'due_on' => '2026-09-06']);
            BehaviourIncident::query()->create(['academic_year_id' => $d['year']->id, 'student_id' => $ahmad->id,
                'behaviour_category_id' => BehaviourCategory::query()->where('degree', 2)->value('id'), 'occurred_on' => '2026-09-02']);
        });
        Sanctum::actingAs($parent);
        $h = $this->schoolHeader($school);

        $this->getJson("/api/v1/my/children/{$ahmad->id}/homework", $h)->assertOk()->assertJsonPath('data.0.title', 'واجب');
        $this->getJson("/api/v1/my/children/{$ahmad->id}/behaviour", $h)->assertOk()->assertJsonPath('data.score.score', 98);
        $this->getJson("/api/v1/my/children/{$ahmad->id}/results", $h)->assertOk();
        $this->getJson("/api/v1/my/children/{$ahmad->id}/transport", $h)->assertOk()->assertJsonPath('data', null);
        $this->getJson('/api/v1/my/announcements', $h)->assertOk();

        $this->getJson("/api/v1/my/children/{$sara->id}/homework", $h)->assertNotFound();
        $this->postJson('/api/v1/my/excuses', ['student_id' => $sara->id, 'from_date' => '2026-09-03', 'to_date' => '2026-09-03', 'reason' => 'x'], $h)->assertNotFound();
        $this->postJson('/api/v1/my/excuses', ['student_id' => $ahmad->id, 'from_date' => '2026-09-03', 'to_date' => '2026-09-03', 'reason' => 'مرض'], $h)->assertCreated();
        $this->getJson('/api/v1/my/excuses', $h)->assertOk()->assertJsonPath('data.0.status', 'pending');
    }
}
