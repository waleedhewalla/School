<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['stage_id', 'name', 'is_default', 'pass_percent'])]
class GradingScale extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'pass_percent' => 'float'];
    }

    /** @return HasMany<GradingBand, $this> highest band first */
    public function bands(): HasMany
    {
        return $this->hasMany(GradingBand::class)->orderByDesc('min_percent');
    }

    /**
     * The scale for a stage: its own scale if it has one, otherwise the
     * school's default scale.
     */
    public static function forSchool(?int $stageId = null): ?self
    {
        if ($stageId !== null && ($own = static::query()->with('bands')->where('stage_id', $stageId)->first())) {
            return $own;
        }

        return static::query()->with('bands')->whereNull('stage_id')->orderByDesc('is_default')->orderBy('id')->first();
    }

    /** The band a percentage falls in: the highest band whose minimum it reaches. */
    public function bandFor(float $percent): ?GradingBand
    {
        return $this->bands->first(fn (GradingBand $band) => $percent >= (float) $band->min_percent);
    }

    public function passes(float $percent): bool
    {
        return $percent >= $this->pass_percent;
    }
}
