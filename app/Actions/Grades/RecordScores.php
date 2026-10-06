<?php

namespace App\Actions\Grades;

use App\Enums\Permission;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScore;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves marks for one subject in one section and term. Teachers may only
 * enter marks for subjects they teach in that section, and only while the
 * term is open for marks; grade managers may always.
 */
class RecordScores
{
    /**
     * @param  list<array{student_id: int, scores: array<int|string, float|string|null>}>  $rows
     *                                                                                            a score is a number, null (not yet marked) or "absent"
     */
    public function handle(Section $section, Term $term, Subject $subject, array $rows, User $by): void
    {
        $this->authorize($section, $term, $subject, $by);

        $components = AssessmentComponent::query()
            ->where('term_id', $term->id)->where('grade_level_id', $section->grade_level_id)->where('subject_id', $subject->id)
            ->get()->keyBy('id');
        $roster = Student::query()->inSection($section->id)->pluck('id')->flip();

        foreach ($rows as $i => $row) {
            if (! $roster->has($row['student_id'])) {
                throw ValidationException::withMessages(["rows.$i.student_id" => __('attendance.not_in_section')]);
            }
            foreach ($row['scores'] as $componentId => $value) {
                $component = $components->get((int) $componentId);
                if ($component === null) {
                    throw ValidationException::withMessages(["rows.$i.scores" => __('grades.unknown_component')]);
                }
                if (is_numeric($value) && ((float) $value < 0 || (float) $value > $component->max_score)) {
                    throw ValidationException::withMessages(["rows.$i.scores.$componentId" => __('grades.score_out_of_range', ['max' => $component->max_score + 0])]);
                }
                if ($value !== null && $value !== 'absent' && ! is_numeric($value)) {
                    throw ValidationException::withMessages(["rows.$i.scores.$componentId" => __('grades.score_not_a_number')]);
                }
            }
        }

        DB::transaction(function () use ($rows, $by) {
            foreach ($rows as $row) {
                foreach ($row['scores'] as $componentId => $value) {
                    $score = AssessmentScore::query()->firstOrNew([
                        'assessment_component_id' => (int) $componentId,
                        'student_id' => $row['student_id'],
                    ]);
                    $score->fill([
                        'score' => is_numeric($value) ? round((float) $value, 2) : null,
                        'is_absent' => $value === 'absent',
                        'recorded_by' => $by->id,
                    ]);

                    if ($score->isDirty() || ! $score->exists) {
                        $score->save();
                    }
                }
            }
        });
    }

    public static function canEnter(Section $section, Term $term, Subject $subject, User $user): bool
    {
        if ($user->can(Permission::GradesManage)) {
            return true;
        }

        return $user->can(Permission::GradesRecord)
            && $term->marks_open
            && $section->teachingAssignments()
                ->where('subject_id', $subject->id)
                ->whereHas('staffMember', fn ($q) => $q->where('user_id', $user->id))
                ->exists();
    }

    private function authorize(Section $section, Term $term, Subject $subject, User $by): void
    {
        if (! self::canEnter($section, $term, $subject, $by)) {
            throw new AuthorizationException($term->marks_open ? __('grades.not_your_subject') : __('grades.marks_closed'));
        }
    }
}
