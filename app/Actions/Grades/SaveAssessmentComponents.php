<?php

namespace App\Actions\Grades;

use App\Models\AssessmentComponent;
use App\Models\GradeLevel;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the assessment components of one subject in one grade and
 * term. Weights must add up to 100. Components that already hold scores
 * can't be removed (their marks would vanish).
 */
class SaveAssessmentComponents
{
    /** @param  list<array{id?: int|null, name_ar: string, name_en?: string|null, max_score: float, weight: float}>  $rows */
    public function handle(Term $term, GradeLevel $grade, Subject $subject, array $rows): void
    {
        $total = round(array_sum(array_column($rows, 'weight')), 2);
        if ($total !== 100.0) {
            throw ValidationException::withMessages(['components' => __('grades.weights_must_total', ['total' => $total])]);
        }

        DB::transaction(function () use ($term, $grade, $subject, $rows) {
            $existing = AssessmentComponent::query()
                ->where('term_id', $term->id)->where('grade_level_id', $grade->id)->where('subject_id', $subject->id)
                ->withCount('scores')->get()->keyBy('id');

            $keep = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id);
            $removed = $existing->except($keep->all());

            if ($used = $removed->firstWhere('scores_count', '>', 0)) {
                throw ValidationException::withMessages(['components' => __('grades.component_has_scores', ['name' => $used->name])]);
            }
            AssessmentComponent::query()->whereKey($removed->keys())->delete();

            foreach ($rows as $i => $row) {
                $attributes = [
                    'term_id' => $term->id,
                    'grade_level_id' => $grade->id,
                    'subject_id' => $subject->id,
                    'name_ar' => $row['name_ar'],
                    'name_en' => $row['name_en'] ?? null,
                    'max_score' => $row['max_score'],
                    'weight' => $row['weight'],
                    'sequence' => $i + 1,
                ];

                if (! empty($row['id']) && $existing->has((int) $row['id'])) {
                    $existing[(int) $row['id']]->update($attributes);
                } else {
                    AssessmentComponent::query()->create($attributes);
                }
            }
        });
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'components' => ['required', 'array', 'min:1', 'max:12'],
            'components.*.id' => ['nullable', 'integer'],
            'components.*.name_ar' => ['required', 'string', 'max:60'],
            'components.*.name_en' => ['nullable', 'string', 'max:60'],
            'components.*.max_score' => ['required', 'numeric', 'min:1', 'max:1000'],
            'components.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
