<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['student_id', 'recorded_by', 'visited_at', 'complaint', 'temperature', 'outcome', 'treatment'])]
class ClinicVisit extends Model
{
    use BelongsToSchool, LogsActivity;

    protected function casts(): array
    {
        return ['visited_at' => 'datetime', 'guardian_notified_at' => 'datetime', 'temperature' => 'decimal:1'];
    }

    public const OUTCOMES = ['returned_to_class', 'rested', 'sent_home', 'referred'];

    /** Outcomes the guardian is told about straight away. */
    public const NOTIFY = ['sent_home', 'referred'];

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
