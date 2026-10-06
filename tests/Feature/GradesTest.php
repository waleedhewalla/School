<?php

namespace Tests\Feature;

use App\Enums\SchoolRole;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScore;
use App\Models\GradingScale;
use App\Models\Subject;
use App\Models\Term;
use App\Support\Grades\TermResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class GradesTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    private function setUpSchool(): array
    {
        $school = $this->createSchool();
        $data = $this->seedSchoolData($school) + ['school' => $school];

        return $data + $this->inSchool($school, fn () => [
            'term' => Term::query()->first(),
            'components' => AssessmentComponent::query()->orderBy('sequence')->get(),
        ]);
    }

    private function marks(array $d, array $rows): TestResponse
    {
        return $this->post('/marks', [
            'term_id' => $d['term']->id, 'section_id' => $d['sectionA']->id, 'subject_id' => $d['subject']->id, 'rows' => $rows,
        ]);
    }

    public function test_weighted_result_and_bands(): void
    {
        $d = $this->setUpSchool();
        [$classwork, $final] = $d['components'];
        [$ahmad, $sara] = $d['students'];

        $this->actingAs($d['teacher']);
        $this->marks($d, [
            ['student_id' => $ahmad->id, 'scores' => [$final->id => 30]],                         // 18/20*40 + 30/40*60 = 81
            ['student_id' => $sara->id, 'scores' => [$classwork->id => 'absent', $final->id => 20]], // 0 + 30 = 30
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $results = $this->inSchool($d['school'], fn () => TermResults::forSection($d['sectionA'], $d['term']));
        $byId = collect($results['students'])->keyBy('student_id');

        $this->assertSame(81.0, $byId[$ahmad->id]['subjects'][$d['subject']->id]['percent']);
        $this->assertSame('جيد جدًا', $byId[$ahmad->id]['subjects'][$d['subject']->id]['grade']);
        $this->assertTrue($byId[$ahmad->id]['subjects'][$d['subject']->id]['passed']);
        $this->assertSame(30.0, $byId[$sara->id]['subjects'][$d['subject']->id]['percent']);
        $this->assertFalse($byId[$sara->id]['subjects'][$d['subject']->id]['passed']);
        $this->assertSame('غير مجتاز', $byId[$sara->id]['average_grade']);
    }

    public function test_missing_component_means_incomplete(): void
    {
        $d = $this->setUpSchool();
        $results = $this->inSchool($d['school'], fn () => TermResults::forSection($d['sectionA'], $d['term']));

        $ahmad = collect($results['students'])->firstWhere('student_id', $d['students'][0]->id);
        $this->assertFalse($ahmad['subjects'][$d['subject']->id]['complete']);
        $this->assertNull($ahmad['average']);
    }

    public function test_mark_entry_rules(): void
    {
        $d = $this->setUpSchool();
        [, $final] = $d['components'];
        $ahmad = $d['students'][0];
        $this->actingAs($d['teacher']);

        $this->marks($d, [['student_id' => $ahmad->id, 'scores' => [$final->id => 45]]])
            ->assertSessionHasErrors(["rows.0.scores.{$final->id}" => 'الدرجة يجب أن تكون بين 0 و 40.']);

        // A subject the teacher doesn't teach in this section.
        $science = $this->inSchool($d['school'], fn () => Subject::query()->create(['code' => 'SCI', 'name_ar' => 'العلوم']));
        $this->post('/marks', ['term_id' => $d['term']->id, 'section_id' => $d['sectionA']->id, 'subject_id' => $science->id, 'rows' => []])->assertForbidden();

        // Closed term: teacher refused, manager allowed.
        $this->inSchool($d['school'], fn () => $d['term']->update(['marks_open' => false]));
        $this->marks($d, [['student_id' => $ahmad->id, 'scores' => [$final->id => 30]]])->assertForbidden();

        $this->actingAs($this->memberOf($d['school'], SchoolRole::Principal));
        $this->marks($d, [['student_id' => $ahmad->id, 'scores' => [$final->id => 30]]])->assertSessionHasNoErrors();
        $this->assertSame(30.0, $this->inSchool($d['school'], fn () => AssessmentScore::query()->where('assessment_component_id', $final->id)->value('score')));
    }

    public function test_marks_page_lists_only_the_teachers_classes(): void
    {
        $d = $this->setUpSchool();
        $this->actingAs($d['teacher']);

        $this->get("/marks?term_id={$d['term']->id}&section_id={$d['sectionA']->id}&subject_id={$d['subject']->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Marks/Entry')
                ->has('classes', 1)
                ->where('sheet.canEdit', true)
                ->has('sheet.components', 2)
                ->where('sheet.students.0.name', 'سارة العتيبي'));
    }

    public function test_assessment_setup_weights_and_copy(): void
    {
        $d = $this->setUpSchool();
        $this->actingAs($this->memberOf($d['school'], SchoolRole::SchoolAdmin));
        $science = $this->inSchool($d['school'], fn () => Subject::query()->create(['code' => 'SCI', 'name_ar' => 'العلوم']));
        $base = ['term_id' => $d['term']->id, 'grade_level_id' => $d['grade']->id];

        $this->post('/grading', $base + ['subject_id' => $science->id, 'components' => [
            ['name_ar' => 'أعمال', 'max_score' => 20, 'weight' => 30],
            ['name_ar' => 'نهائي', 'max_score' => 40, 'weight' => 60],
        ]])->assertSessionHasErrors(['components' => 'مجموع الأوزان يجب أن يكون 100٪ (المجموع الحالي 90٪).']);

        // Removing a component that already has marks is refused.
        [$classwork, $final] = $d['components'];
        $this->post('/grading', $base + ['subject_id' => $d['subject']->id, 'components' => [
            ['id' => $final->id, 'name_ar' => 'نهائي', 'max_score' => 40, 'weight' => 100],
        ]])->assertSessionHasErrors('components');

        $this->post('/grading/copy', $base + ['subject_id' => $d['subject']->id])->assertSessionHas('success');
        $this->assertSame(2, $this->inSchool($d['school'], fn () => AssessmentComponent::query()->where('subject_id', $science->id)->count()));
    }

    public function test_grading_scale_needs_a_zero_band(): void
    {
        $d = $this->setUpSchool();
        $this->actingAs($this->memberOf($d['school'], SchoolRole::SchoolAdmin));

        $this->put('/settings/grading', ['pass_percent' => 60, 'bands' => [
            ['min_percent' => 90, 'label_ar' => 'ممتاز'], ['min_percent' => 60, 'label_ar' => 'ناجح'],
        ]])->assertSessionHasErrors('bands');

        $this->put('/settings/grading', ['pass_percent' => 60, 'bands' => [
            ['min_percent' => 90, 'label_ar' => 'ممتاز'], ['min_percent' => 60, 'label_ar' => 'ناجح'], ['min_percent' => 0, 'label_ar' => 'راسب'],
        ]])->assertSessionHasNoErrors();

        $this->assertSame(['ممتاز', 'ناجح', 'راسب'], $this->inSchool($d['school'], fn () => GradingScale::forSchool()->bands->pluck('label_ar')->all()));
    }

    public function test_publishing_results_and_report_cards(): void
    {
        $d = $this->setUpSchool();
        [$ahmad, $sara] = $d['students'];
        $parent = $this->memberOf($d['school'], SchoolRole::Guardian);
        $this->inSchool($d['school'], fn () => $ahmad->guardians()->first()->user()->associate($parent)->save());
        $url = "/report-cards/{$d['sectionA']->id}/{$d['term']->id}";

        // Staff print the whole section, in Arabic, one card per student.
        $this->actingAs($this->memberOf($d['school'], SchoolRole::Principal));
        $this->get($url)->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('محمد العتيبي')
            ->assertSee('سارة العتيبي')
            ->assertSee('شهادة');

        // Guardians: nothing before publishing.
        $this->actingAs($parent)->get("$url?student_id={$ahmad->id}")->assertForbidden();
        $this->get('/my/children')->assertInertia(fn (Assert $page) => $page->has('children.0.results', 0));

        $this->actingAs($this->memberOf($d['school'], SchoolRole::SchoolAdmin))
            ->patch("/terms/{$d['term']->id}", ['published' => true, 'marks_open' => false])->assertSessionHas('success');

        $this->actingAs($parent)->get("$url?student_id={$ahmad->id}")->assertOk()->assertSee('محمد العتيبي')->assertDontSee('سارة العتيبي');
        $this->get("$url?student_id={$sara->id}")->assertForbidden();
        $this->get($url)->assertForbidden();
        $this->get('/my/children')->assertInertia(fn (Assert $page) => $page
            ->has('children.0.results', 1)
            ->where('children.0.results.0.url', "$url?student_id={$ahmad->id}"));
    }

    public function test_teacher_cannot_set_up_or_publish(): void
    {
        $d = $this->setUpSchool();
        $this->actingAs($d['teacher']);

        $this->get('/grading')->assertForbidden();
        $this->patch("/terms/{$d['term']->id}", ['published' => true])->assertForbidden();
        $this->get('/settings/grading')->assertForbidden();
    }
}
