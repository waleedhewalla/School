<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['student_id', 'section_id', 'attendance_code_id', 'recorded_by', 'date', 'period', 'note'])]
class AttendanceRecord extends Model
{
    use BelongsToSchool;

    /** Period 0 is the daily register. */
    public const DAILY = 0;

    /**
     * Stored as a plain Y-m-d so registers can be looked up by date on
     * every database (the "date" cast would add a time on SQLite).
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : Carbon::parse($value)->startOfDay(),
            set: fn (mixed $value) => Carbon::parse($value)->toDateString(),
        );
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<AttendanceCode, $this> */
    public function code(): BelongsTo
    {
        return $this->belongsTo(AttendanceCode::class, 'attendance_code_id');
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
