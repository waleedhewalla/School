<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['name', 'starts_on', 'ends_on', 'is_current'])]
class AcademicYear extends Model
{
    use BelongsToSchool, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /** @return HasMany<Term, $this> */
    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('sequence');
    }

    /** @return HasMany<Section, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /** Make this the school's only current year. */
    public function makeCurrent(): void
    {
        static::query()->whereKeyNot($this->getKey())->update(['is_current' => false]);
        $this->forceFill(['is_current' => true])->save();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
