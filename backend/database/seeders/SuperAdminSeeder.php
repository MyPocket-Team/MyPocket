<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@mypocket.app'],
            [
                'nom' => 'Super',
                'prenom' => 'Admin',
                'password' => Hash::make('change_moi_immediatement'),
                'role' => 'super_admin',
                'profil_complete' => true,
                'actif' => true,
            ]
        );
    }
}
