<?php

namespace App\Http\Controllers;

use App\Models\ItemStatus;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class OrderItemController extends Controller
{
    // Un producto solo avanza de a un paso: pending -> in_production -> done -> packed -> shipped.
    private const TRANSITIONS = [
        'pending' => ['in_production'],
        'in_production' => ['done'],
        'done' => ['packed'],
        'packed' => ['shipped'],
        'shipped' => [],
    ];

    #[OA\Patch(
        path: '/api/order-items/{id}/status',
        summary: 'Cambiar el estado de un producto del pedido',
        description: 'Marca un producto del pedido como en fabricación, hecho, embalado o enviado. Cuando todos los productos del pedido tienen el mismo estado, el pedido pasa solo a ese estado. Un producto solo se puede enviar cuando todo el pedido está embalado. No se puede usar en pedidos sin confirmar, cancelados, enviados ni entregados. Administradores o vendedores con el permiso update_item_status.',
        tags: ['Seguimiento y Envíos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del producto dentro del pedido (order_items.id)', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['in_production', 'done', 'packed', 'shipped'], example: 'done'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Estado del producto actualizado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Producto del pedido no encontrado.'),
            new OA\Response(response: 422, description: 'Estado inválido o cambio no permitido.'),
        ]
    )]
    public function updateStatus(Request $request, string $id)
    {
        $item = OrderItem::with('status', 'order.status')->find($id);

        if (!$item) {
            return response()->json([
                'message' => 'Producto del pedido no encontrado.',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'status' => ['required', 'string', Rule::in(['in_production', 'done', 'packed', 'shipped'])],
            ], [
                'status.required' => 'El estado es obligatorio.',
                'status.in' => 'El estado debe ser in_production, done, packed o shipped.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $order = $item->order;
        $orderStatus = $order->status->name;

        if (!in_array($orderStatus, ['confirmed', 'in_production', 'finished', 'packed'], true)) {
            return response()->json([
                'message' => 'Solo se puede cambiar el estado de los productos de pedidos confirmados que todavía no se enviaron.',
            ], 422);
        }

        $current = $item->status->name;
        $new = $validated['status'];
        $allowed = self::TRANSITIONS[$current] ?? [];

        if (!in_array($new, $allowed, true)) {
            return response()->json([
                'message' => "No se puede pasar un producto de {$current} a {$new}.",
                'allowed' => $allowed,
            ], 422);
        }

        if ($new === 'shipped' && $orderStatus !== 'packed') {
            return response()->json([
                'message' => 'Para enviar, todos los productos del pedido tienen que estar embalados.',
            ], 422);
        }

        DB::transaction(function () use ($item, $order, $new, $request) {
            $item->update(['item_status_id' => ItemStatus::where('name', $new)->value('id')]);

            $order->syncStatusFromItems($request->user()->id);
        });

        return response()->json([
            'message' => 'Estado del producto actualizado correctamente.',
            'order' => $order->fresh()->load('status', 'items.product:id,name', 'items.status'),
        ], 200);
    }
}
