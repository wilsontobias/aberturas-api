<?php

namespace Database\Seeders;

use App\Models\UserType;
use Illuminate\Database\Seeder;

class UserTypeSeeder extends Seeder
{
    public function run(): void
    {
        UserType::create(['name' => 'customer']);
        UserType::create(['name' => 'seller']);
    }
}