<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasBilingualName;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['user_id', 'campus_id', 'employee_number', 'name_ar', 'name_en', 'national_id', 'job_title', 'phone', 'email', 'status'])]
class StaffMember extends Model
{
    use BelongsToSchool, HasBilingualName, LogsActivity, SoftDeletes;

    protected $attributes = ['status' => 'active'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TeachingAssignment, $this> */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    /** Stored normalized (9665XXXXXXXX) so lookups and SMS work however it was typed. */
    protected function phone(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : (PhoneNumber::normalize($value) ?? $value));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
