<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasBilingualName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stage_id', 'name_ar', 'name_en', 'sequence'])]
class GradeLevel extends Model
{
    use BelongsToSchool, HasBilingualName;

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }
}
