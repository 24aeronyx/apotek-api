<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = Permission::pluck('name')->all();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])
            ->syncPermissions($permissions);

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web'])
            ->syncPermissions($permissions);

        Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web'])
            ->syncPermissions(['create-rme', 'view-rme']);

        Role::firstOrCreate(['name' => 'nurse', 'guard_name' => 'web'])
            ->syncPermissions(['view-rme']);

        Role::firstOrCreate(['name' => 'pharmacist', 'guard_name' => 'web'])
            ->syncPermissions([
                'manage-master-drugs',
                'view-stock',
                'manage-stock-in',
                'process-pos-sale',
            ]);

        Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web'])
            ->syncPermissions(['process-pos-sale']);
    }

    public function down(): void
    {
        // Permission assignments are intentionally preserved on rollback.
    }
};
