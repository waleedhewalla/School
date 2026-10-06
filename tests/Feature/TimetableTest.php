<?php

namespace Tests\Feature;

use App\Enums\SchoolRole;
use App\Models\Period;
use App\Models\Section;
use App\Models\StaffMember;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\TimetableEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class TimetableTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    /** The seeded teacher also teaches science in section B; a second teacher teaches Arabic in B. */
    private function setUpSchool(): array
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school) + ['school' => $school];

        return $data + $this->inSchool($school, function () use ($data) {
            $science = Subject::query()->create(['code' => 'SCI', 'name_ar' => 'العلوم']);
            $arabic = Subject::query()->create(['code' => 'ARB', 'name_ar' => 'اللغة العربية']);
            $other = StaffMember::query()->create(['employee_number' => 'T2', 'name_ar' => 'معلم ثان']);

            return [
                'scienceB' => TeachingAssignment::query()->create(['academic_year_id' => $data['year']->id, 'section_id' => $data['sectionB']->id, 'subject_id' => $science->id, 'staff_member_id' => $data['staff']->id]),
                'arabicB' => TeachingAssignment::query()->create(['academic_year_id' => $data['year']->id, 'section_id' => $data['sectionB']->id, 'subject_id' => $arabic->id, 'staff_member_id' => $other->id]),
                'otherTeacher' => $other,
                'p1' => Period::query()->where('sequence', 1)->first(),
                'p2' => Period::query()->where('sequence', 2)->first(),
                'break' => Period::query()->where('is_break', true)->first(),
            ];
        });
    }

    public function test_place_lessons_and_refuse_double_booking(): void
    {
        $d = $this->setUpSchool();
        Sanctum::actingAs($this->memberOf($d['school'], SchoolRole::SchoolAdmin));
        $headers = $this->schoolHeader($d['school']);
        $url = "/api/v1/sections/{$d['sectionB']->id}/timetable?lang=ar";

        // The seeded teacher is already in section A on Sunday period 1.
        $this->putJson($url, ['day' => 0, 'period_id' => $d['p1']->id, 'teaching_assignment_id' => $d['scienceB']->id], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('errors.teaching_assignment_id.0', 'المعلم لديه حصة في نفس الوقت مع الصف الأول الابتدائي / أ.');

        $this->putJson($url, ['day' => 0, 'period_id' => $d['p2']->id, 'teaching_assignment_id' => $d['scienceB']->id, 'room' => 'مختبر 1'], $headers)->assertCreated();
        $this->putJson($url, ['day' => 0, 'period_id' => $d['p1']->id, 'teaching_assignment_id' => $d['arabicB']->id], $headers)->assertCreated();

        // Replacing a slot keeps one lesson per slot.
        $this->putJson($url, ['day' => 0, 'period_id' => $d['p1']->id, 'teaching_assignment_id' => $d['arabicB']->id, 'room' => '12'], $headers)->assertCreated();

        $grid = $this->getJson("/api/v1/sections/{$d['sectionB']->id}/timetable", $headers)->assertOk()->json('data');
        $this->assertCount(5, $grid['days']);
        $this->assertCount(8, $grid['periods']);
        $this->assertSame('اللغة العربية', $grid['cells']['0-'.$d['p1']->id]['subject']);
        $this->assertSame('مختبر 1', $grid['cells']['0-'.$d['p2']->id]['room']);
    }

    public function test_room_break_day_and_section_checks(): void
    {
        $d = $this->setUpSchool();
        Sanctum::actingAs($this->memberOf($d['school'], SchoolRole::SchoolAdmin));
        $headers = $this->schoolHeader($d['school']);
        $this->inSchool($d['school'], fn () => TimetableEntry::query()->where('section_id', $d['sectionA']->id)->update(['room' => 'مختبر 1']));
        $url = "/api/v1/sections/{$d['sectionB']->id}/timetable";

        $this->putJson($url, ['day' => 0, 'period_id' => $d['p1']->id, 'teaching_assignment_id' => $d['arabicB']->id, 'room' => 'مختبر 1'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('room');
        $this->putJson($url, ['day' => 0, 'period_id' => $d['break']->id, 'teaching_assignment_id' => $d['arabicB']->id], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('period_id');
        $this->putJson($url, ['day' => 5, 'period_id' => $d['p1']->id, 'teaching_assignment_id' => $d['arabicB']->id], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('day');

        $mathA = $this->inSchool($d['school'], fn () => TeachingAssignment::query()->where('section_id', $d['sectionA']->id)->value('id'));
        $this->putJson($url, ['day' => 1, 'period_id' => $d['p1']->id, 'teaching_assignment_id' => $mathA], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('teaching_assignment_id');
    }

    public function test_reassigning_a_teacher_moves_their_lessons_unless_busy(): void
    {
        $d = $this->setUpSchool();
        Sanctum::actingAs($this->memberOf($d['school'], SchoolRole::SchoolAdmin));
        $headers = $this->schoolHeader($d['school']);

        // Arabic in B on Sunday period 1, taught by the second teacher.
        $this->putJson("/api/v1/sections/{$d['sectionB']->id}/timetable", ['day' => 0, 'period_id' => $d['p1']->id, 'teaching_assignment_id' => $d['arabicB']->id], $headers)->assertCreated();

        // Giving B's Arabic to the seeded teacher clashes with their section A lesson.
        $this->postJson('/api/v1/teaching-assignments', [
            'section_id' => $d['sectionB']->id, 'subject_id' => $d['arabicB']->subject_id, 'staff_member_id' => $d['staff']->id,
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('staff_member_id');

        $this->assertSame($d['otherTeacher']->id, $this->inSchool($d['school'], fn () => $d['arabicB']->fresh()->staff_member_id));
    }

    public function test_teacher_sees_their_week_and_todays_lessons_but_cannot_edit(): void
    {
        $d = $this->setUpSchool();
        $this->travelTo('2026-10-04 08:00:00'); // a Sunday
        $this->actingAs($d['teacher']);

        $this->get('/my/timetable')->assertInertia(fn (Assert $page) => $page
            ->component('Timetable/Mine')
            ->where('grid.cells.0-'.$d['p1']->id.'.section', 'الصف الأول الابتدائي / أ'));

        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->has('lessonsToday', 1)
            ->where('lessonsToday.0.section_id', $d['sectionA']->id)
            ->where('lessonsToday.0.period_sequence', 1));

        $this->post("/timetable/{$d['sectionB']->id}", ['day' => 1, 'period_id' => $d['p1']->id, 'teaching_assignment_id' => $d['arabicB']->id])->assertForbidden();
        $this->get('/settings/periods')->assertForbidden();
    }

    public function test_bell_schedule_edits(): void
    {
        $d = $this->setUpSchool();
        $this->actingAs($this->memberOf($d['school'], SchoolRole::Principal));
        $periods = $this->inSchool($d['school'], fn () => Period::query()->orderBy('sequence')->get());

        $payload = fn (array $rows) => ['school_days' => [0, 1, 2, 3, 4], 'periods' => $rows];
        $row = fn (Period $p, array $over = []) => $over + ['id' => $p->id, 'name_ar' => $p->name_ar, 'starts_at' => $p->startsAtShort(), 'ends_at' => $p->endsAtShort(), 'is_break' => $p->is_break];

        // Removing period 1 is refused: a lesson is scheduled in it.
        $this->put('/settings/periods', $payload($periods->slice(1)->map(fn ($p) => $row($p))->values()->all()))
            ->assertSessionHasErrors('periods');

        // Overlapping times are refused.
        $rows = $periods->map(fn ($p) => $row($p))->all();
        $rows[1]['starts_at'] = '07:30';
        $this->put('/settings/periods', $payload($rows))->assertSessionHasErrors('periods.1.starts_at');

        // Drop the last period (unused), add an 8th, and add Saturday.
        $rows = $periods->slice(0, 7)->map(fn ($p) => $row($p))->values()->all();
        $rows[] = ['id' => null, 'name_ar' => 'الحصة الإضافية', 'starts_at' => '12:00', 'ends_at' => '12:40', 'is_break' => false];
        $this->put('/settings/periods', ['school_days' => [6, 0, 1, 2, 3, 4], 'periods' => $rows])->assertSessionHasNoErrors();

        $this->inSchool($d['school'], function () use ($d) {
            $this->assertSame('الحصة الإضافية', Period::query()->where('sequence', 8)->value('name_ar'));
            $this->assertSame(1, TimetableEntry::query()->count());
            $this->assertSame([0, 1, 2, 3, 4, 6], $d['school']->fresh()->schoolDays());
        });
    }
}
