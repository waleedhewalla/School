<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Student;
use App\Models\User;

/**
 * Staff see students through their permissions; guardians see only the
 * children linked to them; a student sees only themself.
 */
class StudentPolicy
{
    public function view(User $user, Student $student): bool
    {
        return $user->can(Permission::StudentsView)
            || $this->isGuardianOf($user, $student)
            || ($student->user_id !== null && (int) $student->user_id === (int) $user->id);
    }

    public function isGuardianOf(User $user, Student $student): bool
    {
        return $student->guardians()->where('guardians.user_id', $user->id)->exists();
    }
}
