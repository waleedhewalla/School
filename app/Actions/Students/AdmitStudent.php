<?php

namespace App\Actions\Students;

use App\Models\AcademicYear;
use App\Models\Family;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates a student with their guardians and (optionally) their first
 * enrollment. Siblings are grouped automatically: if any guardian already
 * belongs to a family, the new student joins it.
 */
class AdmitStudent
{
    public function __construct(private EnrollStudent $enroll) {}

    /**
     * @param  array<string, mixed>  $data  validated StudentRequest data
     */
    public function handle(array $data): Student
    {
        return DB::transaction(function () use ($data) {
            $guardians = collect($data['guardians'] ?? [])->map(fn (array $row) => [
                'model' => isset($row['guardian_id'])
                    ? Guardian::query()->findOrFail($row['guardian_id'])
                    : new Guardian(Arr::except($row, ['relationship', 'is_primary'])),
                'relationship' => $row['relationship'],
                'is_primary' => (bool) ($row['is_primary'] ?? false),
            ]);

            $family = $guardians->pluck('model')->first(fn (Guardian $g) => $g->family_id !== null)?->family
                ?? Family::query()->create(['name' => $data['family_name_ar']]);

            $student = new Student(Arr::except($data, ['guardians', 'enrollment']));
            $student->student_number ??= $this->nextStudentNumber();
            $student->family()->associate($family);
            $student->save();

            foreach ($guardians as $row) {
                /** @var Guardian $guardian */
                $guardian = $row['model'];
                $guardian->family_id ??= $family->id;
                $guardian->save();
                $student->guardians()->attach($guardian->id, [
                    'relationship' => $row['relationship'],
                    'is_primary' => $row['is_primary'],
                ]);
            }

            if (isset($data['enrollment'])) {
                $this->enroll->handle(
                    $student,
                    AcademicYear::query()->findOrFail($data['enrollment']['academic_year_id']),
                    GradeLevel::query()->findOrFail($data['enrollment']['grade_level_id']),
                    isset($data['enrollment']['section_id']) ? Section::query()->findOrFail($data['enrollment']['section_id']) : null,
                );
            }

            return $student;
        });
    }

    /** Next sequential number in this school, e.g. 000042. */
    private function nextStudentNumber(): string
    {
        $last = Student::withTrashed()
            ->lockForUpdate()
            ->pluck('student_number')
            ->filter(fn ($number) => ctype_digit($number))
            ->map(fn ($number) => (int) $number)
            ->max() ?? 0;

        return str_pad((string) ($last + 1), 6, '0', STR_PAD_LEFT);
    }
}
