<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The five roles (CLAUDE.md §1 rule 4) and their permissions (§4).
 * Later sprints add the permissions for their modules here.
 */
class RoleSeeder extends Seeder
{
    /** permission => roles that hold it */
    public const PERMISSIONS = [
        // M1 / M9 Users and roles: assign roles, deactivate, reactivate
        'users.manage' => [User::ROLE_SYSADMIN],
        // M1 Assisted recovery and resending activation links
        'users.assist' => [User::ROLE_SYSADMIN, User::ROLE_URDC],
        // M8 Activity log
        'activity.view' => [User::ROLE_SYSADMIN, User::ROLE_URDC],
        // M2 Records and metadata, file slots, bulk import, publish and withdraw
        'records.manage' => [User::ROLE_URDC],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (User::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }

        foreach (self::PERMISSIONS as $permission => $roles) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (User::ROLES as $role) {
            $granted = array_keys(array_filter(self::PERMISSIONS, fn (array $roles) => in_array($role, $roles, true)));
            Role::findByName($role, 'web')->syncPermissions($granted);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
