<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['assessment_component_id', 'student_id', 'score', 'is_absent', 'recorded_by'])]
class AssessmentScore extends Model
{
    use BelongsToSchool, LogsActivity;

    protected function casts(): array
    {
        return ['score' => 'float', 'is_absent' => 'boolean'];
    }

    /** @return BelongsTo<AssessmentComponent, $this> */
    public function component(): BelongsTo
    {
        return $this->belongsTo(AssessmentComponent::class, 'assessment_component_id');
    }

    /** Every mark change is kept in the audit log. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['score', 'is_absent'])->logOnlyDirty();
    }
}
