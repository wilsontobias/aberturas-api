<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('product_categories')
                ->restrictOnDelete();
            $table->foreignId('material_id')
                ->constrained('materials')
                ->restrictOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unsignedInteger('width_cm');
            $table->unsignedInteger('height_cm');
            $table->string('color', 50)->nullable();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->string('image')->nullable();
            $table->boolean('status')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};