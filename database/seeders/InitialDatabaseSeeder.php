<?php

namespace Database\Seeders;

use App\Models\DrugCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class InitialDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Reset Cache Permission Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Inisialisasi Permissions
        $permissions = [
            'manage-users',
            'manage-master-drugs',
            'view-stock',
            'manage-stock-in',
            'create-rme',
            'view-rme',
            'process-pos-sale',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // 3. Inisialisasi Roles
        $adminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $adminRole->syncPermissions(Permission::all());

        $apotekerRole = Role::firstOrCreate(['name' => 'Apoteker']);
        $apotekerRole->syncPermissions(['manage-master-drugs', 'view-stock', 'manage-stock-in', 'process-pos-sale']);

        // 4. Buat User Super Admin
        $user = User::firstOrCreate(
            ['email' => 'admin@apotek.com'],
            [
                'name' => 'Administrator Apotek',
                'password' => Hash::make('password123'),
            ]
        );
        $user->assignRole($adminRole);

        // 5. Kategori Obat Default
        $categories = ['Obat Bebas', 'Obat Bebas Terbatas', 'Obat Keras', 'Alat Kesehatan', 'Vitamin & Suplemen'];
        foreach ($categories as $cat) {
            DrugCategory::firstOrCreate(['name' => $cat]);
        }
    }
}