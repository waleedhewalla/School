<?php

namespace Tests\Feature;

use App\Enums\SchoolRole;
use App\Models\ExternalExam;
use App\Models\ExternalExamResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Concerns\CreatesSchools;
use Tests\TestCase;

class ExamsAndAnalyticsTest extends TestCase
{
    use CreatesSchools, RefreshDatabase;

    public function test_nafes_results_by_hand_and_from_excel(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);
        [$ahmad, $sara] = $d['students'];
        $this->inSchool($school, fn () => $ahmad->update(['national_id' => $this->saudiId(31)]));
        $this->actingAs($this->memberOf($school, SchoolRole::Principal));

        $this->post('/external-exams', ['type' => 'nafes', 'name' => 'نافس — رياضيات', 'grade_level_id' => $d['grade']->id, 'max_score' => 100])->assertRedirect();
        $exam = $this->inSchool($school, fn () => ExternalExam::query()->sole());

        $this->put("/external-exams/{$exam->id}/results", ['scores' => [$sara->id => 120]])->assertSessionHasErrors('scores.'.$sara->id);
        $this->put("/external-exams/{$exam->id}/results", ['scores' => [$sara->id => 70]])->assertSessionHasNoErrors();

        $path = tempnam(sys_get_temp_dir(), 'n').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['رقم الهوية', 'الدرجة']));
        $writer->addRow(Row::fromValues([$this->saudiId(31), 92]));
        $writer->addRow(Row::fromValues(['9999999999', 50]));
        $writer->close();
        $this->post("/external-exams/{$exam->id}/import", ['file' => new UploadedFile($path, 'nafes.xlsx', null, null, true)])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->inSchool($school, fn () => ExternalExamResult::query()->count()));
        $this->get("/external-exams/{$exam->id}")->assertInertia(fn (Assert $page) => $page->component('ExternalExams/Show')
            ->where('bySection.0.average', 81)->where('bands.0.count', 1));

        // Teachers can look but not enter.
        $this->actingAs($d['teacher'])->put("/external-exams/{$exam->id}/results", ['scores' => [$sara->id => 1]])->assertForbidden();
    }

    public function test_analytics_for_management_only(): void
    {
        $school = $this->createSchool();
        $d = $this->seedSchoolData($school);

        $this->actingAs($d['teacher'])->get('/analytics')->assertForbidden();
        $this->actingAs($this->memberOf($school, SchoolRole::Principal))->get('/analytics')
            ->assertInertia(fn (Assert $page) => $page->component('Analytics/Index')->where('tiles.students', 2)->has('weekly', 1)->where('seeGrades', true));

        // A registrar sees attendance and enrolment, not grades or behaviour.
        $this->actingAs($this->memberOf($school, SchoolRole::Registrar))->get('/analytics')
            ->assertInertia(fn (Assert $page) => $page->where('seeGrades', false)->has('subjects', 0)->where('tiles.behaviour', null));
    }
}
