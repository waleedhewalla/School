<?php

namespace App\Actions\Schools;

use App\Enums\SchoolRole;
use App\Models\Membership;
use App\Models\School;
use App\Models\User;

class AddSchoolMember
{
    public function handle(School $school, User $user, SchoolRole ...$roles): Membership
    {
        $membership = Membership::query()->firstOrCreate(
            ['user_id' => $user->getKey(), 'school_id' => $school->getKey()],
            ['status' => 'active'],
        );

        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId($school->getKey());

        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->assignRole(array_map(fn (SchoolRole $role) => $role->value, $roles));

        setPermissionsTeamId($previousTeam);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $membership;
    }
}
