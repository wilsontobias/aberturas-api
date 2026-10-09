<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
        });

        Schema::create('admin_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_action_id')
                ->constrained('admin_actions')
                ->restrictOnDelete();
            $table->foreignId('admin_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('target_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('detail', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_action_logs');
        Schema::dropIfExists('admin_actions');
    }
};