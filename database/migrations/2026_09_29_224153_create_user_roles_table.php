<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('role_id')
                ->constrained('roles')
                ->restrictOnDelete();
            $table->foreignId('user_type_id')
                ->nullable()
                ->constrained('user_types')
                ->restrictOnDelete();
            $table->boolean('status')->default(true);
            $table->boolean('is_owner')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};