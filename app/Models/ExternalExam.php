<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A national or external test (Nafes, Qiyas Tahsili / Qudrat, …) and the school's results in it. */
#[Fillable(['academic_year_id', 'grade_level_id', 'subject_id', 'type', 'name', 'held_on', 'max_score'])]
class ExternalExam extends Model
{
    use BelongsToSchool;

    public const TYPES = ['nafes', 'tahsili', 'qudrat', 'other'];

    protected function casts(): array
    {
        return ['held_on' => DateOnly::class, 'max_score' => 'float'];
    }

    /** @return HasMany<ExternalExamResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(ExternalExamResult::class);
    }

    /** @return BelongsTo<GradeLevel, $this> */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
