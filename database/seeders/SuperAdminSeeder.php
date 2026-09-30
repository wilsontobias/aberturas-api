<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'name' => 'Super Administrador',
            'email' => 'superadmin@aberturas.com',
            'password' => 'SuperAdmin123',
        ]);

        $user->userRole()->create([
            'role_id' => Role::where('name', 'SuperAdmin')->value('id'),
            'user_type_id' => null,
            'status' => true,
            'is_owner' => false,
        ]);
    }
}