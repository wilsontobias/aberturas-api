<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->boolean('create_orders')->default(false);
            $table->boolean('view_own_orders')->default(false);
            $table->boolean('view_all_orders')->default(false);
            $table->boolean('verify_payments')->default(false);
            $table->boolean('update_item_status')->default(false);
            $table->boolean('edit_ship_date')->default(false);
            $table->boolean('register_balance_payment')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
    }
};