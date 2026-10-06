<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An online quiz for one section and subject, taken by students with a login. */
#[Fillable(['section_id', 'subject_id', 'created_by', 'title', 'instructions', 'opens_at', 'closes_at', 'time_limit_minutes', 'published', 'show_results'])]
class Quiz extends Model
{
    use BelongsToSchool;

    /** Extra time after the deadline for a submission already on its way. */
    public const GRACE_SECONDS = 120;

    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'closes_at' => 'datetime', 'published' => 'boolean', 'show_results' => 'boolean'];
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return HasMany<QuizQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sequence')->orderBy('id');
    }

    /** @return HasMany<QuizAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function isOpen(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->published && $at->gte($this->opens_at) && $at->lte($this->closes_at);
    }

    /** When an attempt that started at $startedAt must be in. */
    public function deadlineFor(CarbonInterface $startedAt): CarbonInterface
    {
        $limit = $this->time_limit_minutes ? $startedAt->copy()->addMinutes($this->time_limit_minutes) : null;

        return $limit && $limit->lt($this->closes_at) ? $limit : $this->closes_at;
    }

    public function maxScore(): float
    {
        return (float) $this->questions->sum('points');
    }
}
