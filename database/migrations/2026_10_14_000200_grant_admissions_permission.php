<?php

use App\Enums\Permission as PermissionName;
use App\Enums\SchoolRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gives existing schools' admin, principal and registrar roles the new
 * admissions permission, leaving any other role edits alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate(PermissionName::AdmissionsManage, 'web');

        Role::query()
            ->whereIn('name', [SchoolRole::SchoolAdmin->value, SchoolRole::Principal->value, SchoolRole::Registrar->value])
            ->each(fn (Role $role) => $role->givePermissionTo(PermissionName::AdmissionsManage));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', PermissionName::AdmissionsManage)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
