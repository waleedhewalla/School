<?php

namespace Tests\Feature\Web;

use App\Enums\SchoolRole;
use App\Models\AcademicYear;
use App\Models\Campus;
use App\Models\Section;
use App\Models\StaffMember;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\TimetableEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class SchoolSetupTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    public function test_admin_creates_a_year_with_terms_and_switches_the_current_year(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $this->actingAs($this->memberOf($school, SchoolRole::SchoolAdmin));

        $this->post('/setup/years', [
            'name' => '1449', 'starts_on' => '2027-08-22', 'ends_on' => '2028-06-08',
            'terms' => [
                ['name_ar' => 'الأول', 'starts_on' => '2027-08-22', 'ends_on' => '2028-01-06'],
                ['name_ar' => 'الثاني', 'starts_on' => '2028-01-16', 'ends_on' => '2028-06-08'],
            ],
        ])->assertSessionHasNoErrors();

        $this->post('/setup/years', ['name' => '1449', 'starts_on' => '2027-08-22', 'ends_on' => '2028-06-08'])->assertSessionHasErrors('name');

        $new = $this->inSchool($school, fn () => AcademicYear::query()->where('name', '1449')->firstOrFail());
        $this->assertSame(2, $this->inSchool($school, fn () => $new->terms()->count()));

        $this->put("/setup/years/{$new->id}", ['is_current' => true])->assertSessionHasNoErrors();
        $this->assertTrue($this->inSchool($school, fn () => $new->refresh()->is_current));
        $this->assertFalse($this->inSchool($school, fn () => $d['year']->refresh()->is_current));

        // A term must sit inside its year.
        $this->post("/setup/years/{$new->id}/terms", ['name_ar' => 'صيفي', 'starts_on' => '2028-07-01', 'ends_on' => '2028-08-01'])->assertSessionHasErrors('ends_on');

        // The old year has sections, so it stays.
        $this->delete("/setup/years/{$d['year']->id}")->assertSessionHasErrors('year');

        $this->get('/setup/years')->assertInertia(fn (Assert $page) => $page->component('Setup/Years')->has('years', 2));
    }

    public function test_sections_are_unique_per_grade_and_kept_while_they_have_students(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $this->actingAs($this->memberOf($school, SchoolRole::Principal));

        $payload = ['academic_year_id' => $d['year']->id, 'grade_level_id' => $d['grade']->id, 'name' => 'ج', 'capacity' => 25];
        $this->post('/setup/sections', $payload)->assertSessionHasNoErrors();
        $this->post('/setup/sections', $payload)->assertSessionHasErrors('name');

        $new = $this->inSchool($school, fn () => Section::query()->where('name', 'ج')->firstOrFail());
        $this->put("/setup/sections/{$new->id}", ['name' => 'د', 'capacity' => 20])->assertSessionHasNoErrors();
        $this->assertSame('د', $this->inSchool($school, fn () => $new->refresh()->name));

        $this->delete("/setup/sections/{$d['sectionA']->id}")->assertSessionHasErrors('section');
        $this->delete("/setup/sections/{$new->id}")->assertSessionHasNoErrors();

        $this->get("/setup/sections?year={$d['year']->id}")
            ->assertInertia(fn (Assert $page) => $page->component('Setup/Sections')->has('sections', 2)->where('sections.0.enrollments_count', 2));
    }

    public function test_subjects_crud_and_assigned_subjects_are_kept(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $this->actingAs($this->memberOf($school, SchoolRole::Principal));

        $this->post('/setup/subjects', ['code' => 'ART', 'name_ar' => 'الفنية'])->assertSessionHasNoErrors();
        $this->post('/setup/subjects', ['code' => 'ART', 'name_ar' => 'أخرى'])->assertSessionHasErrors('code');

        $art = $this->inSchool($school, fn () => Subject::query()->where('code', 'ART')->firstOrFail());
        $this->put("/setup/subjects/{$art->id}", ['name_ar' => 'التربية الفنية', 'sequence' => 5])->assertSessionHasNoErrors();
        $this->delete("/setup/subjects/{$d['subject']->id}")->assertSessionHasErrors('subject');
        $this->delete("/setup/subjects/{$art->id}")->assertSessionHasNoErrors();
    }

    public function test_staff_records_and_teaching_assignments(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $this->actingAs($this->memberOf($school, SchoolRole::Principal));
        $teacherUser = $this->memberOf($school, SchoolRole::Teacher);

        $this->post('/staff', ['employee_number' => 'T9', 'name_ar' => 'أ. نورة', 'user_id' => $teacherUser->id])->assertSessionHasNoErrors();
        $this->post('/staff', ['employee_number' => 'T9', 'name_ar' => 'مكرر'])->assertSessionHasErrors('employee_number');
        $noura = $this->inSchool($school, fn () => StaffMember::query()->where('employee_number', 'T9')->firstOrFail());

        // A user from another school cannot be linked.
        $stranger = $this->memberOf($this->createSchool(), SchoolRole::Teacher);
        $this->put("/staff/{$noura->id}", ['user_id' => $stranger->id])->assertSessionHasErrors('user_id');

        // Reassign math in section A to Noura, then unassign it (its lessons go too).
        $this->post("/staff/assignments/{$d['sectionA']->id}", ['subject_id' => $d['subject']->id, 'staff_member_id' => $noura->id, 'is_homeroom' => true])->assertSessionHasNoErrors();
        $assignment = $this->inSchool($school, fn () => TeachingAssignment::query()->where('section_id', $d['sectionA']->id)->sole());
        $this->assertSame($noura->id, $assignment->staff_member_id);
        $this->assertSame(1, $this->inSchool($school, fn () => TimetableEntry::query()->where('staff_member_id', $noura->id)->count()));

        $this->post("/staff/assignments/{$d['sectionA']->id}", ['subject_id' => $d['subject']->id, 'staff_member_id' => null])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->inSchool($school, fn () => TeachingAssignment::query()->count() + TimetableEntry::query()->count()));

        $this->get('/staff')->assertInertia(fn (Assert $page) => $page->component('Staff/Index')->has('staff', 2));
        $this->get("/staff/assignments?section={$d['sectionA']->id}")->assertInertia(fn (Assert $page) => $page->component('Staff/Assignments'));
    }

    public function test_school_profile_and_campuses(): void
    {
        $school = $this->createSchool();
        $this->actingAs($this->memberOf($school, SchoolRole::SchoolAdmin));

        $this->put('/settings/school', ['name_ar' => 'مدارس الريادة', 'default_locale' => 'en', 'date_display' => 'hijri', 'timezone' => 'Asia/Dubai'])->assertSessionHasNoErrors();
        $this->assertSame('Asia/Dubai', $school->refresh()->timezone);
        $this->put('/settings/school', ['name_ar' => 'x', 'default_locale' => 'fr', 'date_display' => 'hijri', 'timezone' => 'Asia/Dubai'])->assertSessionHasErrors('default_locale');

        $this->post('/settings/campuses', ['name_ar' => 'مبنى البنات', 'gender' => 'female'])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->inSchool($school, fn () => Campus::query()->count()));
    }

    public function test_teachers_cannot_reach_setup_screens(): void
    {
        $school = $this->createSchool();
        $this->actingAs($this->memberOf($school, SchoolRole::Teacher));

        foreach (['/setup/years', '/setup/sections', '/setup/subjects', '/staff', '/staff/assignments', '/settings/school'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_cannot_edit_another_schools_section(): void
    {
        $other = $this->createSchool();
        $foreign = $this->seedSchoolData($other)['sectionB'];
        $school = $this->createSchool();
        $this->actingAs($this->memberOf($school, SchoolRole::SchoolAdmin));

        $this->put("/setup/sections/{$foreign->id}", ['name' => 'x'])->assertNotFound();
        $this->delete("/setup/sections/{$foreign->id}")->assertNotFound();
    }
}
