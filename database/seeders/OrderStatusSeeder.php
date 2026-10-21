<?php

namespace Database\Seeders;

use App\Models\OrderStatus;
use Illuminate\Database\Seeder;

class OrderStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            'pending',
            'confirmed',
            'in_production',
            'ready',
            'shipped',
            'delivered',
            'cancelled',
        ];

        foreach ($statuses as $name) {
            OrderStatus::firstOrCreate(['name' => $name]);
        }
    }
}
