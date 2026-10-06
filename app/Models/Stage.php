<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasBilingualName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name_ar', 'name_en', 'sequence'])]
class Stage extends Model
{
    use BelongsToSchool, HasBilingualName;

    /** @return HasMany<GradeLevel, $this> */
    public function gradeLevels(): HasMany
    {
        return $this->hasMany(GradeLevel::class)->orderBy('sequence');
    }
}
