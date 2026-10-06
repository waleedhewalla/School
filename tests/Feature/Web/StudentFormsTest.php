<?php

namespace Tests\Feature\Web;

use App\Enums\SchoolRole;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class StudentFormsTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    public function test_admit_from_the_web_form_linking_a_sibling(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        $this->inSchool($school, fn () => $data['students'][0]->guardians()->first()->update(['phone' => '0559998888']));
        $this->actingAs($this->memberOf($school, SchoolRole::Registrar));

        $this->get('/students/create')->assertInertia(fn (Assert $page) => $page
            ->component('Students/Form')
            ->where('year.id', $data['year']->id)
            ->has('grades', 15)
            ->has('sections', 2));

        $match = $this->getJson('/guardians/lookup?q=0559998888')->assertOk()->json('data.0');
        $this->assertSame(['محمد العتيبي'], $match['children']);

        $this->post('/students', [
            'first_name_ar' => 'خالد',
            'family_name_ar' => 'العتيبي',
            'gender' => 'male',
            'guardians' => [['guardian_id' => $match['id'], 'relationship' => 'father', 'is_primary' => true]],
            'enrollment' => ['academic_year_id' => $data['year']->id, 'grade_level_id' => $data['grade']->id, 'section_id' => $data['sectionB']->id],
        ])->assertRedirect()->assertSessionHas('success');

        $this->inSchool($school, function () use ($data) {
            $khalid = Student::query()->where('first_name_ar', 'خالد')->sole();
            $this->assertSame($data['students'][0]->family_id, $khalid->family_id);
        });
    }

    public function test_validation_errors_come_back_in_arabic(): void
    {
        $school = $this->createSchool();
        $this->actingAs($this->memberOf($school, SchoolRole::Registrar));

        $this->post('/students', ['first_name_ar' => '', 'family_name_ar' => 'س', 'gender' => 'x'])
            ->assertSessionHasErrors([
                'first_name_ar' => 'حقل الاسم الأول مطلوب.',
                'gender' => 'القيمة المختارة لـ الجنس غير صحيحة.',
            ]);
    }

    public function test_edit_move_and_withdraw(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        $student = $data['students'][0];
        $this->actingAs($this->memberOf($school, SchoolRole::Registrar));

        $this->get("/students/{$student->id}")->assertInertia(fn (Assert $page) => $page
            ->where('canManage', true)
            ->where('otherSections.0.id', $data['sectionB']->id));

        $this->put("/students/{$student->id}", ['name_en' => 'Mohammed Al-Otaibi'])->assertRedirect("/students/{$student->id}");

        $enrollmentId = $this->inSchool($school, fn () => $student->enrollments()->first()->id);
        $this->patch("/enrollments/{$enrollmentId}", ['section_id' => $data['sectionB']->id])->assertSessionHasNoErrors();
        $this->patch("/enrollments/{$enrollmentId}", ['status' => 'withdrawn', 'left_on' => '2026-10-01'])->assertSessionHasNoErrors();

        $this->inSchool($school, function () use ($student) {
            $fresh = $student->fresh();
            $this->assertSame('Mohammed Al-Otaibi', $fresh->name_en);
            $this->assertSame('withdrawn', $fresh->status);
        });
    }

    public function test_teacher_cannot_use_the_forms(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        $this->actingAs($data['teacher']);

        $this->get('/students/create')->assertForbidden();
        $this->get("/students/{$data['students'][0]->id}/edit")->assertForbidden();
        $this->get('/promotions')->assertForbidden();
        $this->get('/guardians/lookup?q=0559998888')->assertForbidden();
        $this->get("/students/{$data['students'][0]->id}")->assertInertia(fn (Assert $page) => $page->where('canManage', false));
    }

    public function test_promote_a_section_from_the_web(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        [$ahmad, $sara] = $data['students'];

        [$next, $grade2A] = $this->inSchool($school, function () use ($data) {
            $next = AcademicYear::query()->create(['name' => '1449', 'starts_on' => '2027-08-22', 'ends_on' => '2028-06-08']);
            $grade2 = GradeLevel::query()->where('stage_id', $data['grade']->stage_id)->where('sequence', 2)->first();

            return [$next, Section::query()->create(['academic_year_id' => $next->id, 'grade_level_id' => $grade2->id, 'name' => 'أ'])];
        });

        $this->actingAs($this->memberOf($school, SchoolRole::Registrar));

        $this->get("/promotions?from={$data['year']->id}&to={$next->id}&section_id={$data['sectionA']->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Promotions/Index')
                ->has('students', 2)
                ->where('targetSections.has_next_grade', true)
                ->where('targetSections.promoted.0.id', $grade2A->id));

        $this->post('/promotions', [
            'from' => $data['year']->id,
            'to' => $next->id,
            'decisions' => [
                ['student_id' => $ahmad->id, 'outcome' => 'promoted', 'section_id' => $grade2A->id],
                ['student_id' => $sara->id, 'outcome' => 'repeated', 'section_id' => null],
            ],
        ])->assertSessionHas('success');

        $this->inSchool($school, fn () => $this->assertSame($grade2A->id, $ahmad->enrollments()->latest('id')->first()->section_id));
    }
}
