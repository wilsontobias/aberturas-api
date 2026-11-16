<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ReportController extends Controller
{
    private const LOW_STOCK = 2;
    private const TIMEZONE = 'America/Argentina/Buenos_Aires';

    #[OA\Get(
        path: '/api/reports/daily-summary',
        summary: 'Resumen diario de pedidos (para n8n)',
        description: 'Devuelve los números de un día (por defecto, ayer): pedidos creados, pedidos confirmados, señas aprobadas, pedidos por estado, los envíos que salen hoy y los productos con poco stock. Lo consulta el flujo programado de n8n. No usa el token de Sanctum: pide el encabezado X-N8N-Token con el token compartido que está en el .env (N8N_TOKEN).',
        tags: ['Reportes'],
        security: [['n8nToken' => []]],
        parameters: [
            new OA\Parameter(name: 'date', description: 'Día a resumir (AAAA-MM-DD). Si no se envía, es ayer.', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-11-08')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Resumen obtenido correctamente.'),
            new OA\Response(response: 401, description: 'Token de n8n inválido o ausente.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function dailySummary(Request $request)
    {
        try {
            $validated = $request->validate([
                'date' => ['nullable', 'date_format:Y-m-d'],
            ], [
                'date.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        // Los días se cuentan en hora argentina, aunque la base guarde otra zona.
        $today = Carbon::now(self::TIMEZONE)->startOfDay();
        $day = isset($validated['date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['date'], self::TIMEZONE)->startOfDay()
            : $today->copy()->subDay();

        $start = $day->copy()->timezone(config('app.timezone'));
        $end = $day->copy()->endOfDay()->timezone(config('app.timezone'));

        $approved = Payment::whereHas('type', fn ($q) => $q->where('name', 'deposit'))
            ->whereHas('status', fn ($q) => $q->where('name', 'approved'))
            ->whereBetween('verified_at', [$start, $end]);

        $byStatus = OrderStatus::withCount('orders')->orderBy('id')->get()
            ->mapWithKeys(fn ($status) => [$status->name => $status->orders_count]);

        $toShip = Order::whereDate('estimated_ship_date', $today->toDateString())
            ->whereHas('status', fn ($q) => $q->whereIn('name', ['confirmed', 'in_production', 'finished', 'packed']))
            ->with('user:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'customer' => $order->user->name,
                'total' => $order->total,
            ]);

        $lowStock = Product::where('status', true)
            ->where('stock', '>', 0)
            ->where('stock', '<=', self::LOW_STOCK)
            ->orderBy('stock')
            ->orderBy('name')
            ->get(['id', 'name', 'stock']);

        return response()->json([
            'message' => 'Resumen obtenido correctamente.',
            'summary' => [
                'date' => $day->toDateString(),
                'today' => $today->toDateString(),
                'new_orders' => Order::whereBetween('created_at', [$start, $end])->count(),
                'confirmed_orders' => Order::whereBetween('confirmed_at', [$start, $end])->count(),
                'deposits_approved' => [
                    'count' => (clone $approved)->count(),
                    'amount' => number_format((float) (clone $approved)->sum('amount'), 2, '.', ''),
                ],
                'awaiting_review' => $byStatus['receipt_in_review'] ?? 0,
                'orders_by_status' => $byStatus,
                'to_ship_today' => $toShip,
                'low_stock' => $lowStock,
            ],
        ], 200);
    }
}
