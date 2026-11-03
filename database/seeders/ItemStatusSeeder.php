<?php

namespace Database\Seeders;

use App\Models\ItemStatus;
use Illuminate\Database\Seeder;

class ItemStatusSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['pending', 'in_production', 'done', 'packed', 'shipped'] as $name) {
            ItemStatus::firstOrCreate(['name' => $name]);
        }
    }
}
