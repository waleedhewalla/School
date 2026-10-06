<?php

namespace Tests\Feature;

use App\Enums\SchoolRole;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScore;
use App\Models\GradeLevel;
use App\Models\GradingScale;
use App\Models\Stage;
use App\Models\Term;
use App\Support\Grades\TermResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class StageScalesAndPdfTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    public function test_new_schools_get_ministry_based_scales(): void
    {
        $school = $this->createSchool();

        $this->inSchool($school, function () {
            $scale = fn (string $stage, ?int $grade = null) => GradingScale::forSchool(
                Stage::query()->where('code', $stage)->value('id'),
                $grade ? GradeLevel::query()->whereHas('stage', fn ($q) => $q->where('code', $stage))->where('sequence', $grade)->value('id') : null,
            );

            $this->assertSame(75.0, $scale('primary', 1)->pass_percent);
            $this->assertSame('متفوق', $scale('primary', 2)->bandFor(96)->label_ar);
            $this->assertSame('مقبول', $scale('primary', 4)->bandFor(55)->label_ar);
            $this->assertSame('مقبول', $scale('intermediate', 1)->bandFor(55)->label_ar);
            $this->assertSame('ممتاز مرتفع', $scale('secondary', 3)->bandFor(97)->label_ar);
            $this->assertSame('ضعيف', $scale('secondary', 3)->bandFor(55)->label_ar);
            $this->assertTrue($scale('kg', 2)->passes(10));
        });
    }

    public function test_grade_then_stage_then_default(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $this->actingAs($this->memberOf($school, SchoolRole::SchoolAdmin));
        $term = $this->inSchool($school, function () use ($d) {
            [, $final] = AssessmentComponent::query()->orderBy('sequence')->get();
            AssessmentScore::query()->create(['assessment_component_id' => $final->id, 'student_id' => $d['students'][0]->id, 'score' => 20]);

            return Term::query()->first();
        });
        // 18/20*40 + 20/40*60 = 66%.
        $result = fn () => $this->inSchool($school, fn () => collect(TermResults::forSection($d['sectionA'], $term)['students'])
            ->firstWhere('student_id', $d['students'][0]->id)['subjects'][$d['subject']->id]);

        // Grade 1's own (mastery) scale.
        $this->assertSame(['غير مجتاز', false], [$result()['grade'], $result()['passed']]);

        // Drop it: the school default applies.
        $this->put('/settings/grading', ['grade_level_id' => $d['grade']->id, 'use_default' => true])->assertSessionHasNoErrors();
        $this->assertSame(['مقبول', true], [$result()['grade'], $result()['passed']]);

        // A primary-stage scale beats the default.
        $this->put('/settings/grading', ['stage_id' => $d['grade']->stage_id, 'pass_percent' => 70, 'bands' => [
            ['min_percent' => 85, 'label_ar' => 'متفوق'], ['min_percent' => 70, 'label_ar' => 'متمكن'], ['min_percent' => 0, 'label_ar' => 'يحتاج دعمًا'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(['يحتاج دعمًا', false], [$result()['grade'], $result()['passed']]);

        // And the stage can go back to the default.
        $this->put('/settings/grading', ['stage_id' => $d['grade']->stage_id, 'use_default' => true])->assertSessionHasNoErrors();
        $this->assertSame('مقبول', $result()['grade']);
    }

    public function test_bulk_pdf_through_gotenberg(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $term = $this->inSchool($school, fn () => Term::query()->first());
        $url = "/report-cards/{$d['sectionA']->id}/{$term->id}/pdf";
        $this->actingAs($this->memberOf($school, SchoolRole::Principal));

        $this->get($url)->assertNotFound(); // PDF_DRIVER=none

        config(['services.pdf.driver' => 'gotenberg', 'services.pdf.gotenberg_url' => 'http://gotenberg:3000']);
        Http::fake(['gotenberg:3000/*' => Http::response('%PDF-1.7 fake', 200)]);

        $this->get($url)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/forms/chromium/convert/html')
            && str_contains($request->body(), 'محمد العتيبي')
            && str_contains($request->body(), 'dir="rtl"'));

        $this->actingAs($this->memberOf($school, SchoolRole::Guardian))->get($url)->assertForbidden();
    }
}
