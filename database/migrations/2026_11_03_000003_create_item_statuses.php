<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
        });

        // Se cargan acá para que funcione también en una base que ya tiene pedidos (sin migrate:fresh).
        foreach (['pending', 'in_production', 'done', 'packed', 'shipped'] as $name) {
            DB::table('item_statuses')->insert(['name' => $name]);
        }

        $ids = DB::table('item_statuses')->pluck('id', 'name');

        Schema::table('order_items', function (Blueprint $table) use ($ids) {
            $table->foreignId('item_status_id')
                ->default($ids['pending'])
                ->constrained('item_statuses')
                ->restrictOnDelete();
        });

        // Pedidos que ya existían: los productos de stock están hechos desde el principio...
        DB::table('order_items')->where('from_stock', true)->update(['item_status_id' => $ids['done']]);

        // ...y el resto sigue el estado en el que está su pedido.
        $byOrder = function (string $orderStatus) {
            return DB::table('orders')
                ->join('order_statuses', 'order_statuses.id', '=', 'orders.order_status_id')
                ->where('order_statuses.name', $orderStatus)
                ->select('orders.id');
        };

        DB::table('order_items')->whereIn('order_id', $byOrder('in_production'))->where('from_stock', false)
            ->update(['item_status_id' => $ids['in_production']]);
        DB::table('order_items')->whereIn('order_id', $byOrder('finished'))->update(['item_status_id' => $ids['done']]);
        DB::table('order_items')->whereIn('order_id', $byOrder('packed'))->update(['item_status_id' => $ids['packed']]);
        DB::table('order_items')->whereIn('order_id', $byOrder('shipped'))->update(['item_status_id' => $ids['shipped']]);
        DB::table('order_items')->whereIn('order_id', $byOrder('delivered'))->update(['item_status_id' => $ids['shipped']]);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_status_id');
        });

        Schema::dropIfExists('item_statuses');
    }
};
