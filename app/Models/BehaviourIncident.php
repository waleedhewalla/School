<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['academic_year_id', 'student_id', 'behaviour_category_id', 'recorded_by', 'occurred_on', 'note', 'action_taken'])]
class BehaviourIncident extends Model
{
    use BelongsToSchool, LogsActivity;

    protected function casts(): array
    {
        return ['occurred_on' => DateOnly::class, 'guardian_notified_at' => 'datetime'];
    }

    /** @return BelongsTo<BehaviourCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BehaviourCategory::class, 'behaviour_category_id');
    }

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
