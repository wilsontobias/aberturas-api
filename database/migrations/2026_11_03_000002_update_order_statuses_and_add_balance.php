<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Estados del pedido. Solo se tocan si la tabla ya tiene datos (una base con pedidos);
        //    en una base nueva los carga el seeder.
        if (DB::table('order_statuses')->exists()) {
            DB::table('order_statuses')->where('name', 'pending')->update(['name' => 'pending_payment']);
            DB::table('order_statuses')->where('name', 'ready')->update(['name' => 'finished']);

            foreach (['receipt_in_review', 'packed'] as $name) {
                DB::table('order_statuses')->insertOrIgnore(['name' => $name]);
            }
        }

        // 2) Saldo y fecha de confirmación.
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('balance_amount', 12, 2)->default(0)->after('deposit_amount');
            $table->timestamp('confirmed_at')->nullable()->after('estimated_ship_date');
        });

        // 3) Se completan los pedidos que ya existían.
        DB::table('orders')->update(['balance_amount' => DB::raw('total - deposit_amount')]);

        DB::statement("UPDATE orders SET confirmed_at = (
            SELECT MIN(h.created_at) FROM order_status_histories h
            JOIN order_statuses s ON s.id = h.order_status_id
            WHERE h.order_id = orders.id AND s.name = 'confirmed'
        )");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['balance_amount', 'confirmed_at']);
        });

        DB::table('order_statuses')->where('name', 'pending_payment')->update(['name' => 'pending']);
        DB::table('order_statuses')->where('name', 'finished')->update(['name' => 'ready']);

        DB::table('order_statuses')
            ->whereIn('name', ['receipt_in_review', 'packed'])
            ->whereNotIn('id', DB::table('orders')->select('order_status_id'))
            ->whereNotIn('id', DB::table('order_status_histories')->select('order_status_id'))
            ->delete();
    }
};
