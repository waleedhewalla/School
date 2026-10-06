<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'family_id', 'student_number', 'national_id', 'first_name_ar', 'father_name_ar',
    'grandfather_name_ar', 'family_name_ar', 'name_en', 'gender', 'date_of_birth',
    'nationality', 'status',
])]
class Student extends Model
{
    use BelongsToSchool, LogsActivity, SoftDeletes;

    protected $attributes = [
        'nationality' => 'SA',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'date_of_birth' => DateOnly::class,
        ];
    }

    /** Full Arabic name: first, father, grandfather, family. */
    protected function nameAr(): Attribute
    {
        return Attribute::get(fn () => collect([
            $this->first_name_ar, $this->father_name_ar, $this->grandfather_name_ar, $this->family_name_ar,
        ])->filter()->implode(' '));
    }

    /** Name in the active language, falling back to Arabic. */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => app()->getLocale() === 'en' && filled($this->name_en) ? $this->name_en : $this->name_ar);
    }

    /** @return BelongsTo<Family, $this> */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /** @return BelongsToMany<Guardian, $this> */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)->withPivot(['relationship', 'is_primary'])->withTimestamps();
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** The enrollment in the school's current academic year, if any. */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $query) => $query->whereHas('academicYear', fn ($year) => $year->where('is_current', true)),
        );
    }

    /** @return HasMany<AttendanceRecord, $this> */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /** @param  Builder<Student>  $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $query->where(fn (Builder $q) => $q
            ->where('student_number', $term)
            ->orWhere('national_id', $term)
            ->orWhere('first_name_ar', 'like', $like)
            ->orWhere('family_name_ar', 'like', $like)
            ->orWhere('name_en', 'like', $like));
    }

    /** @param  Builder<Student>  $query */
    /** @return HasOne<StudentTransport, $this> */
    public function transport(): HasOne
    {
        return $this->hasOne(StudentTransport::class);
    }

    /** @return HasOne<HealthRecord, $this> */
    public function healthRecord(): HasOne
    {
        return $this->hasOne(HealthRecord::class);
    }

    /** @param  Builder<Student>  $query  children of a guardian's login */
    public function scopeGuardedBy(Builder $query, User $user): void
    {
        $query->whereHas('guardians', fn (Builder $q) => $q->where('guardians.user_id', $user->getKey()));
    }

    public function scopeInSection(Builder $query, int $sectionId): void
    {
        $query->whereHas('enrollments', fn (Builder $q) => $q
            ->where('section_id', $sectionId)
            ->where('status', EnrollmentStatus::Active));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
