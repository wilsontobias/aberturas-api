<?php

namespace Database\Seeders;

use App\Models\AdminAction;
use Illuminate\Database\Seeder;

class AdminActionSeeder extends Seeder
{
    public function run(): void
    {
        AdminAction::firstOrCreate(['name' => 'grant_admin']);
        AdminAction::firstOrCreate(['name' => 'revoke_admin']);
        AdminAction::firstOrCreate(['name' => 'grant_owner']);
        AdminAction::firstOrCreate(['name' => 'revoke_owner']);
        AdminAction::firstOrCreate(['name' => 'activate_user']);
        AdminAction::firstOrCreate(['name' => 'deactivate_user']);
        AdminAction::firstOrCreate(['name' => 'change_user_type']);
    }
}