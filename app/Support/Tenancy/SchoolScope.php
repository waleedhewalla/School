<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits queries to the current school. With no school in context the
 * scope fails closed and matches nothing, so a missing context can never
 * leak another school's rows.
 */
class SchoolScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $schoolId = app(CurrentSchool::class)->id();

        if ($schoolId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('school_id'), $schoolId);
    }
}
