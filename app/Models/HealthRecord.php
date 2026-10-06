<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** A student's health card. Health data is sensitive (PDPL): clinic staff and the family only. */
#[Fillable(['student_id', 'blood_type', 'allergies', 'chronic_conditions', 'medications', 'emergency_contact_name', 'emergency_contact_phone', 'notes', 'updated_by'])]
class HealthRecord extends Model
{
    use BelongsToSchool, LogsActivity;

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** Whether there is anything the school should know (allergies, conditions, medication). */
    public function hasAlerts(): bool
    {
        return filled($this->allergies) || filled($this->chronic_conditions) || filled($this->medications);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
