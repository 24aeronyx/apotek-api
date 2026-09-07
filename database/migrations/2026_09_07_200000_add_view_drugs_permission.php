<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'view-drugs',
            'guard_name' => 'web',
        ]);

        foreach (['admin', 'Super Admin', 'pharmacist', 'cashier'] as $roleName) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if ($role) {
                $role->givePermissionTo($permission);
            }
        }
    }

    public function down(): void
    {
        Permission::where('name', 'view-drugs')->delete();
    }
};
