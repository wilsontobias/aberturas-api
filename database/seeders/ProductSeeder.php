<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['Ventanas', 'Aluminio', 'Ventana corrediza de aluminio', 120, 100, 'Blanco', 180000, 10],
            ['Ventanas', 'PVC', 'Ventana de PVC con doble vidrio', 150, 110, 'Blanco', 320000, 4],
            ['Puertas', 'Madera', 'Puerta de madera maciza', 80, 200, 'Roble', 420000, 0],
            ['Puertas', 'Aluminio', 'Puerta de aluminio', 80, 200, 'Negro', 290000, 6],
            ['Portones', 'Hierro', 'Portón de hierro de dos hojas', 300, 200, 'Negro', 850000, 0],
            ['Portones', 'Aluminio', 'Portón levadizo de aluminio', 250, 210, 'Gris', 1200000, 2],
        ];

        foreach ($products as [$category, $material, $name, $width, $height, $color, $price, $stock]) {
            Product::create([
                'category_id' => ProductCategory::where('name', $category)->value('id'),
                'material_id' => Material::where('name', $material)->value('id'),
                'name' => $name,
                'width_cm' => $width,
                'height_cm' => $height,
                'color' => $color,
                'price' => $price,
                'stock' => $stock,
            ]);
        }
    }
}