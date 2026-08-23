<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DoctorSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'dr.andi@klinik.com'],
            [
                'name'     => 'dr. Andi Wijaya, Sp.PD',
                'password' => Hash::make('password123'),
                'role'     => 'doctor',
            ]
        );

        User::updateOrCreate(
            ['email' => 'dr.siti@klinik.com'],
            [
                'name'     => 'dr. Siti Rahma, Sp.A',
                'password' => Hash::make('password123'),
                'role'     => 'doctor',
            ]
        );
    }
}