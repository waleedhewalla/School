<?php

namespace App\Events;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

/**
 * Fired after attendance is saved, with the records whose code asks for
 * guardians to be told (absent, late). Notification channels (SMS,
 * WhatsApp, push) subscribe to this.
 */
class StudentsMarkedAbsent
{
    use Dispatchable;

    /** @param  Collection<int, AttendanceRecord>  $records */
    public function __construct(public int $schoolId, public Collection $records) {}
}
