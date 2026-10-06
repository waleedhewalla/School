<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasBilingualName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['academic_year_id', 'name_ar', 'name_en', 'sequence', 'starts_on', 'ends_on', 'marks_open', 'results_published_at'])]
class Term extends Model
{
    use BelongsToSchool, HasBilingualName;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
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
}
