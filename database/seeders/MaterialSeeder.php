<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        Material::create(['name' => 'Aluminio']);
        Material::create(['name' => 'PVC']);
        Material::create(['name' => 'Madera']);
        Material::create(['name' => 'Hierro']);
    }
}