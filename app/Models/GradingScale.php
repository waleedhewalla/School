<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'is_default', 'pass_percent'])]
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

    public static function forSchool(): ?self
    {
        return static::query()->with('bands')->orderByDesc('is_default')->orderBy('id')->first();
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
