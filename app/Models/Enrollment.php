<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\EnrollmentStatus;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['student_id', 'academic_year_id', 'grade_level_id', 'section_id', 'status', 'enrolled_on', 'left_on'])]
class Enrollment extends Model
{
    use BelongsToSchool, LogsActivity;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'enrolled_on' => DateOnly::class,
            'left_on' => DateOnly::class,
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return BelongsTo<GradeLevel, $this> */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
