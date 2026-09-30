<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->foreignId('locality_id')
                ->constrained('localities')
                ->restrictOnDelete();
            $table->string('phone_1', 30);
            $table->string('phone_2', 30);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};