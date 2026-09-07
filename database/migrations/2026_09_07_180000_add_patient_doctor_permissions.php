<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $patientPermission = Permission::firstOrCreate([
            'name' => 'view-patients',
            'guard_name' => 'web',
        ]);
        $doctorPermission = Permission::firstOrCreate([
            'name' => 'view-doctors',
            'guard_name' => 'web',
        ]);

        foreach (['admin', 'Super Admin'] as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->givePermissionTo([$patientPermission, $doctorPermission]);
        }

        foreach (['doctor', 'nurse'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($patientPermission);
            }
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', ['view-patients', 'view-doctors'])->delete();
    }
};
