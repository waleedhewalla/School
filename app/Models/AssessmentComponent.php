<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasBilingualName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['term_id', 'grade_level_id', 'subject_id', 'name_ar', 'name_en', 'max_score', 'weight', 'sequence'])]
class AssessmentComponent extends Model
{
    use BelongsToSchool, HasBilingualName;

    protected function casts(): array
    {
        return ['max_score' => 'float', 'weight' => 'float'];
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** @return HasMany<AssessmentScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class);
    }
}
