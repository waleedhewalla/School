<?php

namespace Tests\Feature;

use App\Enums\SchoolRole;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScore;
use App\Models\GradingScale;
use App\Models\Term;
use App\Support\Grades\TermResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class StageScalesAndPdfTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    public function test_a_stage_can_have_its_own_scale(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        $this->actingAs($this->memberOf($school, SchoolRole::SchoolAdmin));
        $term = $this->inSchool($school, function () use ($d) {
            [$classwork, $final] = AssessmentComponent::query()->orderBy('sequence')->get();
            AssessmentScore::query()->create(['assessment_component_id' => $final->id, 'student_id' => $d['students'][0]->id, 'score' => 20]);

            return Term::query()->first();
        });
        // 18/20*40 + 20/40*60 = 66% → "مقبول" on the default scale (pass 50).
        $result = fn () => $this->inSchool($school, fn () => collect(TermResults::forSection($d['sectionA'], $term)['students'])
            ->firstWhere('student_id', $d['students'][0]->id)['subjects'][$d['subject']->id]);
        $this->assertSame(['مقبول', true], [$result()['grade'], $result()['passed']]);

        // Primary gets its own stricter scale.
        $this->put('/settings/grading', ['stage_id' => $d['grade']->stage_id, 'pass_percent' => 70, 'bands' => [
            ['min_percent' => 85, 'label_ar' => 'متفوق'], ['min_percent' => 70, 'label_ar' => 'متمكن'], ['min_percent' => 0, 'label_ar' => 'يحتاج دعمًا'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(['يحتاج دعمًا', false], [$result()['grade'], $result()['passed']]);

        // Back to the default.
        $this->put('/settings/grading', ['stage_id' => $d['grade']->stage_id, 'use_default' => true])->assertSessionHasNoErrors();
        $this->assertSame('مقبول', $result()['grade']);
        $this->assertSame(1, $this->inSchool($school, fn () => GradingScale::query()->count()));
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
