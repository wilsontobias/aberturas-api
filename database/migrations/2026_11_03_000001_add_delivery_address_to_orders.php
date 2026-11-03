<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // La referencia de la dirección reemplaza al campo "notes" que ya existía.
            $table->renameColumn('notes', 'address_notes');

            // Nullable solo para que los pedidos viejos (sin dirección) sigan existiendo.
            // Los pedidos nuevos los exige la validación del controlador.
            $table->string('street', 120)->nullable()->after('locality_id');
            $table->string('street_number', 20)->nullable()->after('street');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['street', 'street_number']);
            $table->renameColumn('address_notes', 'notes');
        });
    }
};
