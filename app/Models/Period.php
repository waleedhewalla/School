<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasBilingualName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A slot in the bell schedule. Attendance registers for a lesson use the
 * period's sequence as their period number (0 is the daily register).
 */
#[Fillable(['sequence', 'name_ar', 'name_en', 'starts_at', 'ends_at', 'is_break'])]
class Period extends Model
{
    use BelongsToSchool, HasBilingualName;

    protected function casts(): array
    {
        return ['is_break' => 'boolean'];
    }

    /** @param  Builder<Period>  $query */
    public function scopeLessons(Builder $query): void
    {
        $query->where('is_break', false)->orderBy('sequence');
    }

    /** @return HasMany<TimetableEntry, $this> */
    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    /** "07:00" from a stored time. */
    public function startsAtShort(): string
    {
        return substr((string) $this->starts_at, 0, 5);
    }

    public function endsAtShort(): string
    {
        return substr((string) $this->ends_at, 0, 5);
    }
}
