<?php

namespace App\Actions\Schools;

use App\Enums\Permission as PermissionName;
use App\Enums\SchoolRole;
use App\Models\School;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the default roles for a school (roles are per school; the
 * permission names themselves are global).
 */
class ProvisionSchoolRoles
{
    public function handle(School $school): void
    {
        foreach (PermissionName::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId($school->getKey());

        foreach (SchoolRole::cases() as $roleName) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName->value,
                'guard_name' => 'web',
                'school_id' => $school->getKey(),
            ]);
            $role->syncPermissions($roleName->defaultPermissions());
        }

        setPermissionsTeamId($previousTeam);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
