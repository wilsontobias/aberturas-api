<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class LocalitySeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Entre Ríos' => [
                'Paraná', 'Concordia', 'Gualeguaychú', 'Concepción del Uruguay', 'Villaguay',
                'La Paz', 'Victoria', 'Federal', 'Chajarí', 'Colón',
            ],
            'Corrientes' => [
                'Corrientes', 'Goya', 'Paso de los Libres', 'Curuzú Cuatiá', 'Mercedes',
                'Santo Tomé', 'Esquina', 'Bella Vista', 'Ituzaingó', 'Monte Caseros',
            ],
        ];

        foreach ($data as $provinceName => $localities) {
            $province = Province::create(['name' => $provinceName]);

            foreach ($localities as $localityName) {
                $province->localities()->create(['name' => $localityName]);
            }
        }
    }
}