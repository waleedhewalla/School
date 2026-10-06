<?php

namespace Tests\Feature\Web;

use App\Actions\Schools\AddSchoolMember;
use App\Enums\SchoolRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class WebAppTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    public function test_login_page_is_arabic_rtl_by_default(): void
    {
        $this->get('/login', ['Accept-Language' => 'ar'])->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('locale', 'ar')
                ->where('dir', 'rtl')
                ->where('translations.Sign in', 'تسجيل الدخول'));
    }

    public function test_sign_in_and_out(): void
    {
        $school = $this->createSchool();
        $user = $this->memberOf($school, SchoolRole::SchoolAdmin);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_pages_require_sign_in(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/students')->assertRedirect('/login');
    }

    public function test_user_in_several_schools_picks_one(): void
    {
        $schoolA = $this->createSchool();
        $schoolB = $this->createSchool();
        $outsiderSchool = $this->createSchool();
        $user = $this->memberOf($schoolA, SchoolRole::SchoolAdmin);
        app(AddSchoolMember::class)->handle($schoolB, $user, SchoolRole::Teacher);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/schools');
        $this->get('/schools')->assertInertia(fn (Assert $page) => $page->component('Schools/Select')->has('schools', 2));

        $this->post('/schools', ['school_id' => $outsiderSchool->id])->assertForbidden();
        $this->post('/schools', ['school_id' => $schoolB->id])->assertRedirect('/dashboard');

        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('school.id', $schoolB->id)
            ->where('can', fn ($can) => $can['students.manage'] === false && $can['attendance.record'] === true));
    }

    public function test_dashboard_shows_todays_numbers(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        $this->travelTo('2026-09-01 09:00:00');

        $this->actingAs($this->memberOf($school, SchoolRole::Principal))
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('year.name', '1448')
                ->where('stats.students', 2)
                ->where('stats.sections', 2)
                ->where('stats.registers_taken', 1)
                ->where('stats.absent_today', 0));
    }

    public function test_students_list_and_profile(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        $this->actingAs($this->memberOf($school, SchoolRole::Registrar));

        $this->get('/students?search=سارة')->assertInertia(fn (Assert $page) => $page
            ->component('Students/Index')
            ->has('students.data', 1)
            ->where('students.data.0.name', 'سارة العتيبي')
            ->has('sections', 2));

        $this->get('/students/'.$data['students'][0]->id)->assertInertia(fn (Assert $page) => $page
            ->component('Students/Show')
            ->where('student.data.name_ar', 'محمد العتيبي')
            ->has('student.data.guardians', 1)
            ->has('attendance', 1));
    }

    public function test_teacher_register_lists_only_their_sections_and_saves(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        [$ahmad, $sara] = $data['students'];
        $this->actingAs($data['teacher']);

        $this->get('/attendance')->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/Register')
            ->has('sections', 1)
            ->where('sections.0.id', $data['sectionA']->id)
            ->where('register', null));

        $this->get('/attendance?section_id='.$data['sectionA']->id.'&date=2026-09-03')
            ->assertInertia(fn (Assert $page) => $page
                ->where('canEdit', true)
                ->where('register.taken', false)
                ->has('register.students', 2));

        $this->from('/attendance')->post('/attendance/'.$data['sectionA']->id, [
            'date' => '2026-09-03',
            'period' => 0,
            'records' => [
                ['student_id' => $ahmad->id, 'code' => 'P'],
                ['student_id' => $sara->id, 'code' => 'A', 'note' => 'مريضة'],
            ],
        ])->assertRedirect('/attendance')->assertSessionHas('success');

        $this->post('/attendance/'.$data['sectionB']->id, [
            'date' => '2026-09-03', 'period' => 0, 'records' => [['student_id' => $ahmad->id, 'code' => 'P']],
        ])->assertForbidden();
    }

    public function test_guardian_lands_on_their_children(): void
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school);
        $parent = $this->memberOf($school, SchoolRole::Guardian);
        $this->inSchool($school, fn () => $data['students'][0]->guardians()->first()->user()->associate($parent)->save());

        $this->actingAs($parent)->get('/dashboard')->assertRedirect('/my/children');
        $this->get('/students')->assertForbidden();
        $this->get('/students/'.$data['students'][1]->id)->assertForbidden();

        $this->get('/my/children')->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Children')
            ->has('children', 1)
            ->where('children.0.id', $data['students'][0]->id)
            ->has('children.0.attendance', 1));
    }

    public function test_switching_language_is_saved_to_the_profile(): void
    {
        $user = User::factory()->create(['locale' => 'ar']);

        $this->actingAs($user)->get('/locale/en')->assertRedirect();

        $this->assertSame('en', $user->fresh()->locale);
    }
}
