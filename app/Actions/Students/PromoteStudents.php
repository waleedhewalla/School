<?php

namespace App\Actions\Students;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Section;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * End-of-year promotion. For each student's enrollment in the old year:
 * "promoted" moves to the next grade, "repeated" stays in the same grade,
 * "graduated" leaves the school. The old enrollment is closed, never
 * edited, so the history stays intact.
 */
class PromoteStudents
{
    /**
     * @param  list<array{student_id: int, outcome: string, section_id?: int|null}>  $decisions
     * @return Collection<int, Enrollment> the new enrollments
     */
    public function handle(AcademicYear $from, AcademicYear $to, array $decisions): Collection
    {
        if ($from->is($to)) {
            throw ValidationException::withMessages(['to_academic_year_id' => __('students.same_year')]);
        }

        $order = $this->gradeOrder();

        return DB::transaction(function () use ($from, $to, $decisions, $order) {
            $created = collect();

            foreach ($decisions as $index => $decision) {
                $outcome = EnrollmentStatus::from($decision['outcome']);

                /** @var Enrollment|null $old */
                $old = Enrollment::query()
                    ->where('academic_year_id', $from->id)
                    ->where('student_id', $decision['student_id'])
                    ->where('status', EnrollmentStatus::Active)
                    ->first();

                if ($old === null) {
                    throw ValidationException::withMessages(["decisions.$index.student_id" => __('students.not_enrolled_in_year')]);
                }

                $old->update(['status' => $outcome, 'left_on' => $from->ends_on]);

                if ($outcome === EnrollmentStatus::Graduated) {
                    $old->student->update(['status' => 'graduated']);

                    continue;
                }

                $gradeId = $outcome === EnrollmentStatus::Repeated
                    ? $old->grade_level_id
                    : ($order[$order->search($old->grade_level_id) + 1] ?? null);

                if ($gradeId === null) {
                    throw ValidationException::withMessages(["decisions.$index.outcome" => __('students.no_next_grade')]);
                }

                $section = isset($decision['section_id']) ? Section::query()->findOrFail($decision['section_id']) : null;

                $created->push(app(EnrollStudent::class)->handle(
                    $old->student, $to, GradeLevel::query()->findOrFail($gradeId), $section, $to->starts_on,
                ));
            }

            return $created;
        });
    }

    /** @return Collection<int, int> grade level ids from KG 1 to grade 12 */
    private function gradeOrder(): Collection
    {
        return GradeLevel::query()
            ->join('stages', 'stages.id', '=', 'grade_levels.stage_id')
            ->orderBy('stages.sequence')
            ->orderBy('grade_levels.sequence')
            ->pluck('grade_levels.id')
            ->values();
    }
}
