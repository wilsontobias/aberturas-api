<?php

namespace Database\Seeders;

use App\Models\OrderStatus;
use Illuminate\Database\Seeder;

class OrderStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            'pending_payment',
            'receipt_in_review',
            'confirmed',
            'in_production',
            'finished',
            'packed',
            'shipped',
            'delivered',
            'cancelled',
        ];

        foreach ($statuses as $name) {
            OrderStatus::firstOrCreate(['name' => $name]);
        }
    }
}
