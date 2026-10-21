<?php

namespace Database\Seeders;

use App\Models\PaymentStatus;
use App\Models\PaymentType;
use Illuminate\Database\Seeder;

class PaymentCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['deposit', 'balance'] as $name) {
            PaymentType::firstOrCreate(['name' => $name]);
        }

        foreach (['pending', 'approved', 'rejected'] as $name) {
            PaymentStatus::firstOrCreate(['name' => $name]);
        }
    }
}
