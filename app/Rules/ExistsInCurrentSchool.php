<?php

namespace App\Rules;

use App\Support\Tenancy\CurrentSchool;
use Illuminate\Validation\Rules\Exists;

/**
 * "exists" validation limited to the active school, so a request cannot
 * point at another school's record by id.
 */
class ExistsInCurrentSchool
{
    public static function in(string $table, string $column = 'id'): Exists
    {
        return (new Exists($table, $column))
            ->where('school_id', app(CurrentSchool::class)->id())
            ->whereNull('deleted_at');
    }

    /** For tables without soft deletes. */
    public static function inTable(string $table, string $column = 'id'): Exists
    {
        return (new Exists($table, $column))->where('school_id', app(CurrentSchool::class)->id());
    }
}
