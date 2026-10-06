<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after attendance is saved, with the records whose code asks for
 * guardians to be told (absent, late). Carries ids, not models: queued
 * listeners reload them inside the school's context.
 */
class StudentsMarkedAbsent
{
    use Dispatchable;

    /** @param  list<int>  $recordIds */
    public function __construct(public int $schoolId, public array $recordIds) {}
}
