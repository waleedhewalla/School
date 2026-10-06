<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academic_year_id', 'grade_level_id', 'seats', 'opens_on', 'closes_on', 'born_from', 'born_to', 'exception_days'])]
class AdmissionWindow extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return ['opens_on' => 'date', 'closes_on' => 'date', 'born_from' => 'date', 'born_to' => 'date'];
    }

    /** @return BelongsTo<GradeLevel, $this> */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /** @param  Builder<AdmissionWindow>  $query */
    public function scopeOpenToday(Builder $query): void
    {
        $today = now()->toDateString();
        $query->where('opens_on', '<=', $today)->where('closes_on', '>=', $today);
    }

    public function seatsTaken(): int
    {
        return $this->applications()->whereIn('status', ApplicationStatus::holdingSeat())->count();
    }

    public function seatsLeft(): int
    {
        return max(0, $this->seats - $this->seatsTaken());
    }
}
