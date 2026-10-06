<?php

namespace Tests\Unit;

use App\Models\AssessmentComponent;
use App\Models\AssessmentScore;
use App\Models\GradingBand;
use App\Models\GradingScale;
use App\Support\Grades\TermResults;
use Tests\TestCase;

class TermResultsTest extends TestCase
{
    private function scale(): GradingScale
    {
        $scale = new GradingScale(['pass_percent' => 50]);
        $scale->setRelation('bands', collect([[90, 'ممتاز'], [80, 'جيد جدًا'], [50, 'مقبول'], [0, 'غير مجتاز']])
            ->map(fn ($b) => new GradingBand(['min_percent' => $b[0], 'label_ar' => $b[1]])));

        return $scale;
    }

    private function makeComponent(int $id, float $max, float $weight): AssessmentComponent
    {
        return (new AssessmentComponent(['max_score' => $max, 'weight' => $weight]))->forceFill(['id' => $id]);
    }

    public function test_weighted_percentage_with_band_edges(): void
    {
        $components = collect([$this->makeComponent(1, 10, 50), $this->makeComponent(2, 50, 50)]);
        $scores = collect([
            1 => new AssessmentScore(['score' => 9]),
            2 => new AssessmentScore(['score' => 45]),
        ]);

        $result = TermResults::subjectResult($components, $scores, $this->scale());

        $this->assertSame(['percent' => 90.0, 'grade' => 'ممتاز', 'passed' => true, 'complete' => true], $result);
    }

    public function test_weights_not_totalling_100_are_normalised(): void
    {
        $components = collect([$this->makeComponent(1, 20, 20), $this->makeComponent(2, 20, 20)]);
        $scores = collect([1 => new AssessmentScore(['score' => 10]), 2 => new AssessmentScore(['score' => 10])]);

        $this->assertSame(50.0, TermResults::subjectResult($components, $scores, $this->scale())['percent']);
    }

    public function test_absent_counts_zero_and_missing_is_incomplete(): void
    {
        $components = collect([$this->makeComponent(1, 10, 40), $this->makeComponent(2, 10, 60)]);

        $absent = collect([1 => new AssessmentScore(['is_absent' => true]), 2 => new AssessmentScore(['score' => 10])]);
        $this->assertSame(60.0, TermResults::subjectResult($components, $absent, $this->scale())['percent']);

        $missing = collect([2 => new AssessmentScore(['score' => 10])]);
        $this->assertFalse(TermResults::subjectResult($components, $missing, $this->scale())['complete']);
    }
}
