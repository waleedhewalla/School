<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasBilingualName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name_ar', 'name_en', 'kind', 'degree', 'points', 'notify_guardian', 'active', 'sequence'])]
class BehaviourCategory extends Model
{
    use BelongsToSchool, HasBilingualName;

    public const POSITIVE = 'positive';

    public const NEGATIVE = 'negative';

    protected function casts(): array
    {
        return ['notify_guardian' => 'boolean', 'active' => 'boolean', 'degree' => 'integer', 'points' => 'integer'];
    }

    /** @return HasMany<BehaviourIncident, $this> */
    public function incidents(): HasMany
    {
        return $this->hasMany(BehaviourIncident::class);
    }

    /** Points this category adds to (positive) or takes from (negative) the score. */
    public function signedPoints(): int
    {
        return $this->kind === self::POSITIVE ? $this->points : -$this->points;
    }

    /** Copies the starting categories from config into the current school. */
    public static function seedDefaults(): void
    {
        foreach (config('madrasa_behaviour.categories') as $i => [$ar, $en, $kind, $degree, $points, $notify]) {
            static::query()->create([
                'name_ar' => $ar, 'name_en' => $en, 'kind' => $kind, 'degree' => $degree,
                'points' => $points, 'notify_guardian' => $notify, 'sequence' => $i + 1,
            ]);
        }
    }
}
