<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Role Standar
        $roles = ['admin', 'doctor', 'nurse', 'pharmacist', 'cashier'];
        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        // 2. Buat Admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@klinik.com'],
            [
                'name'      => 'Administrator Utama',
                'username'  => 'admin',
                'password'  => Hash::make('password123'),
                'is_active' => true,
            ]
        );
        $admin->assignRole('admin');

        // 3. Buat Dokter Dummy
        $doctor = User::updateOrCreate(
            ['email' => 'dr.andi@klinik.com'],
            [
                'name'      => 'dr. Andi Wijaya, Sp.PD',
                'username'  => 'drandi',
                'password'  => Hash::make('password123'),
                'phone'     => '081234567890',
                'is_active' => true,
            ]
        );
        $doctor->assignRole('doctor');
    }
}