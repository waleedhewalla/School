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

    /**
     * For existing schools after an upgrade: gives every default role the
     * default permissions it lacks. Never removes anything, so a school's
     * own role edits survive.
     */
    public static function grantMissingDefaults(): void
    {
        foreach (PermissionName::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (SchoolRole::cases() as $roleName) {
            Role::query()->where('name', $roleName->value)->each(function (Role $role) use ($roleName) {
                $missing = array_diff($roleName->defaultPermissions(), $role->permissions()->pluck('name')->all());
                if ($missing) {
                    $role->givePermissionTo($missing);
                }
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
