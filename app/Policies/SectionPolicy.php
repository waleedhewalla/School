<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Section;
use App\Models\User;

class SectionPolicy
{
    /** Admins and registrars record for any section; teachers only for sections they teach. */
    public function recordAttendance(User $user, Section $section): bool
    {
        return $user->can(Permission::AttendanceManage)
            || ($user->can(Permission::AttendanceRecord) && $section->isTaughtBy($user));
    }

    public function viewAttendance(User $user, Section $section): bool
    {
        return $user->can(Permission::AttendanceView) || $this->recordAttendance($user, $section);
    }
}
