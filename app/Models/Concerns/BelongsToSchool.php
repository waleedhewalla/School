<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Support\Tenancy\CurrentSchool;
use App\Support\Tenancy\SchoolContextMismatch;
use App\Support\Tenancy\SchoolScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as tenant data: reads are filtered to the current school,
 * new rows get the current school's id, and writes for any other school
 * are refused.
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::saving(function (Model $model) {
            $currentId = app(CurrentSchool::class)->id();

            if ($model->school_id === null) {
                $model->school_id = $currentId;
            }

            if ($model->school_id === null || (int) $model->school_id !== $currentId) {
                throw SchoolContextMismatch::forModel($model::class, $currentId, $model->school_id);
            }
        });
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
