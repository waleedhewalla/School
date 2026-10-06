<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasBilingualName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academic_year_id', 'name_ar', 'name_en', 'sequence', 'starts_on', 'ends_on', 'marks_open', 'results_published_at'])]
class Term extends Model
{
    use BelongsToSchool, HasBilingualName;

    protected function casts(): array
    {
        return [
            'starts_on' => DateOnly::class,
            'ends_on' => DateOnly::class,
            'marks_open' => 'boolean',
            'results_published_at' => 'datetime',
        ];
    }

    public function resultsPublished(): bool
    {
        return $this->results_published_at !== null;
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** @return HasMany<AssessmentComponent, $this> */
    public function assessmentComponents(): HasMany
    {
        return $this->hasMany(AssessmentComponent::class);
    }
}
