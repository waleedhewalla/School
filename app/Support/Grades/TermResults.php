<?php

namespace App\Support\Grades;

use App\Models\AssessmentComponent;
use App\Models\AssessmentScore;
use App\Models\GradingScale;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * Works out term results for a section: for every student and subject,
 * the weighted percentage of the assessment components, its grade band,
 * and whether it passes. A subject is "incomplete" until every component
 * has a score (absence counts as zero); incomplete subjects get no grade
 * and are left out of the overall average.
 */
class TermResults
{
    /**
     * @param  Collection<int, Student>|null  $students  defaults to the section's enrolled students
     * @return array{subjects: list<array{id: int, name: string}>, students: list<array<string, mixed>>, scale: ?array}
     */
    public static function forSection(Section $section, Term $term, ?Collection $students = null): array
    {
        $scale = GradingScale::forSchool();
        $students ??= Student::query()->inSection($section->id)->orderBy('family_name_ar')->orderBy('first_name_ar')->get();

        $components = AssessmentComponent::query()
            ->with('subject')
            ->where('term_id', $term->id)
            ->where('grade_level_id', $section->grade_level_id)
            ->orderBy('subject_id')->orderBy('sequence')
            ->get()
            ->groupBy('subject_id');

        $scores = AssessmentScore::query()
            ->whereIn('assessment_component_id', $components->flatten()->pluck('id'))
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => $rows->keyBy('assessment_component_id'));

        $subjects = $components->map(fn (Collection $list) => $list->first()->subject)
            ->sortBy([['sequence', 'asc'], ['code', 'asc']])->values();

        return [
            'subjects' => $subjects->map(fn (Subject $s) => ['id' => $s->id, 'name' => $s->name])->all(),
            'scale' => $scale ? ['pass_percent' => $scale->pass_percent] : null,
            'students' => $students->map(function (Student $student) use ($subjects, $components, $scores, $scale) {
                $results = [];
                foreach ($subjects as $subject) {
                    $results[$subject->id] = self::subjectResult($components[$subject->id], $scores->get($student->id, collect()), $scale);
                }

                $complete = collect($results)->where('complete', true);
                $average = $complete->isEmpty() ? null : round($complete->avg('percent'), 2);

                return [
                    'student_id' => $student->id,
                    'student_number' => $student->student_number,
                    'name' => $student->name,
                    'subjects' => $results,
                    'average' => $average,
                    'average_grade' => $average !== null ? $scale?->bandFor($average)?->label : null,
                    'complete' => $complete->count() === count($results) && $results !== [],
                ];
            })->all(),
        ];
    }

    /**
     * @param  Collection<int, AssessmentComponent>  $components
     * @param  Collection<int, AssessmentScore>  $scores  keyed by component id
     * @return array{percent: ?float, grade: ?string, passed: ?bool, complete: bool}
     */
    public static function subjectResult(Collection $components, Collection $scores, ?GradingScale $scale): array
    {
        $totalWeight = $components->sum('weight');
        $earned = 0.0;
        $complete = $totalWeight > 0;

        foreach ($components as $component) {
            $score = $scores->get($component->id);

            if ($score === null || ($score->score === null && ! $score->is_absent)) {
                $complete = false;

                continue;
            }

            $value = $score->is_absent ? 0.0 : (float) $score->score;
            $earned += $component->max_score > 0 ? ($value / $component->max_score) * $component->weight : 0;
        }

        if (! $complete) {
            return ['percent' => null, 'grade' => null, 'passed' => null, 'complete' => false];
        }

        $percent = round($earned / $totalWeight * 100, 2);

        return [
            'percent' => $percent,
            'grade' => $scale?->bandFor($percent)?->label,
            'passed' => $scale?->passes($percent),
            'complete' => true,
        ];
    }
}
