<?php

namespace App\Http\Controllers;

use App\Models\ItemStatus;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class OrderController extends Controller
{
    private const DEPOSIT_IN_STOCK = 10;
    private const DEPOSIT_MANUFACTURING = 50;

    #[OA\Post(
        path: '/api/orders',
        summary: 'Crear pedido',
        description: 'Crea un pedido con sus productos y la dirección de entrega (localidad, calle, número y referencia). Descuenta el stock disponible. Seña del 10 % si todo está en stock, 50 % si algo hay que fabricarlo. Requiere el permiso create_orders.',
        tags: ['Pedidos'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['locality_id', 'street', 'street_number', 'items'],
                properties: [
                    new OA\Property(property: 'locality_id', type: 'integer', example: 1),
                    new OA\Property(property: 'street', type: 'string', example: 'San Martín'),
                    new OA\Property(property: 'street_number', type: 'string', example: '1234'),
                    new OA\Property(property: 'address_notes', type: 'string', nullable: true, example: 'Portón negro, tocar el timbre.'),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'product_id', type: 'integer', example: 1),
                                new OA\Property(property: 'quantity', type: 'integer', example: 2),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Pedido creado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para crear pedidos.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function store(Request $request)
    {
        $user = $request->user()->load('customerProfile');

        if (!$user->customerProfile) {
            return response()->json([
                'message' => 'Solo los clientes con perfil pueden crear pedidos.',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'items' => ['required', 'array', 'min:1', 'max:50'],
                'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('status', true)],
                'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
                'locality_id' => ['required', 'integer', Rule::exists('localities', 'id')->where('status', true)],
                'street' => ['required', 'string', 'max:120', 'regex:/^[\pL\d\s.\'°,-]+$/u'],
                'street_number' => ['required', 'string', 'max:20', 'regex:/^[\pL\d\s\/.-]+$/u'],
                'address_notes' => ['nullable', 'string', 'max:255'],
            ], $this->messages());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $productIds = collect($validated['items'])->pluck('product_id');

        try {
            $order = DB::transaction(function () use ($validated, $user, $productIds) {
                $products = Product::whereIn('id', $productIds)
                    ->where('status', true)
                    ->whereHas('category', fn ($q) => $q->where('status', true))
                    ->whereHas('material', fn ($q) => $q->where('status', true))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($products->count() !== $productIds->count()) {
                    throw ValidationException::withMessages([
                        'items' => ['Algún producto no está disponible en el catálogo.'],
                    ]);
                }

                $itemStatusIds = ItemStatus::pluck('id', 'name');
                $total = 0;
                $requiresManufacturing = false;
                $itemsData = [];

                foreach ($validated['items'] as $item) {
                    $product = $products[$item['product_id']];
                    $quantity = (int) $item['quantity'];
                    $fromStock = $product->stock >= $quantity;

                    if ($fromStock) {
                        $product->decrement('stock', $quantity);
                    } else {
                        $requiresManufacturing = true;
                    }

                    $subtotal = round($product->price * $quantity, 2);
                    $total += $subtotal;

                    $itemsData[] = [
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $product->price,
                        'subtotal' => $subtotal,
                        'from_stock' => $fromStock,
                        'item_status_id' => $itemStatusIds[$fromStock ? 'done' : 'pending'],
                    ];
                }

                $percentage = $requiresManufacturing
                    ? self::DEPOSIT_MANUFACTURING
                    : self::DEPOSIT_IN_STOCK;

                $order = Order::create([
                    'user_id' => $user->id,
                    'order_status_id' => OrderStatus::where('name', 'pending_payment')->value('id'),
                    'locality_id' => $validated['locality_id'],
                    'street' => $validated['street'],
                    'street_number' => $validated['street_number'],
                    'total' => round($total, 2),
                    'deposit_percentage' => $percentage,
                    'deposit_amount' => round($total * $percentage / 100, 2),
                    'balance_amount' => round($total - round($total * $percentage / 100, 2), 2),
                    'requires_manufacturing' => $requiresManufacturing,
                    'address_notes' => $validated['address_notes'] ?? null,
                ]);

                $order->items()->createMany($itemsData);

                $order->statusHistory()->create([
                    'order_status_id' => $order->order_status_id,
                    'changed_by' => $user->id,
                    'note' => 'Pedido creado.',
                ]);

                return $order;
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Pedido creado correctamente.',
            'order' => $order->load('status', 'locality:id,name', 'items.product:id,name', 'items.status'),
        ], 201);
    }

    #[OA\Get(
        path: '/api/orders/my',
        summary: 'Ver mis pedidos',
        description: 'Devuelve los pedidos del usuario dueño del token, del más nuevo al más viejo.',
        tags: ['Pedidos'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Pedidos obtenidos correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
        ]
    )]
    public function myOrders(Request $request)
    {
        $orders = $request->user()
            ->orders()
            ->with('status', 'items.product:id,name', 'items.status')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Pedidos obtenidos correctamente.',
            'orders' => $orders,
        ], 200);
    }

    #[OA\Get(
        path: '/api/orders/{id}',
        summary: 'Ver un pedido',
        description: 'Devuelve un pedido con sus productos. Lo puede ver su dueño, un administrador o un vendedor con el permiso view_all_orders.',
        tags: ['Pedidos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del pedido', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pedido obtenido correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No podés ver pedidos de otros usuarios.'),
            new OA\Response(response: 404, description: 'Pedido no encontrado.'),
        ]
    )]
    public function show(Request $request, string $id)
    {
        $order = Order::with('user:id,name,email', 'status', 'locality.province', 'items.product:id,name', 'items.status')
            ->find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Pedido no encontrado.',
            ], 404);
        }

        $viewer = $request->user();

        if ($order->user_id !== $viewer->id && !$this->canViewAll($viewer)) {
            return response()->json([
                'message' => 'No podés ver pedidos de otros usuarios.',
            ], 403);
        }

        return response()->json([
            'message' => 'Pedido obtenido correctamente.',
            'order' => $order,
        ], 200);
    }

    #[OA\Get(
        path: '/api/orders',
        summary: 'Listar todos los pedidos',
        description: 'Devuelve los pedidos de todos los clientes, con el nombre y los teléfonos de cada cliente, del más nuevo al más viejo, paginados. Filtro opcional por estado. Administradores o vendedores con el permiso view_all_orders.',
        tags: ['Pedidos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'status', description: 'Nombre del estado', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pending_payment', 'receipt_in_review', 'confirmed', 'in_production', 'finished', 'packed', 'shipped', 'delivered', 'cancelled'])),
            new OA\Parameter(name: 'page', description: 'Número de página (empieza en 1)', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
            new OA\Parameter(name: 'per_page', description: 'Pedidos por página (de 1 a 100)', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pedidos obtenidos correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function index(Request $request)
    {
        try {
            $request->validate([
                'status' => ['sometimes', Rule::exists('order_statuses', 'name')],
                'page' => ['sometimes', 'integer', 'min:1'],
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ], [
                'status.exists' => 'El estado indicado no existe.',
                'page.integer' => 'La página debe ser un número entero.',
                'page.min' => 'La página debe ser 1 o mayor.',
                'per_page.integer' => 'La cantidad por página debe ser un número entero.',
                'per_page.min' => 'La cantidad por página debe ser al menos 1.',
                'per_page.max' => 'La cantidad por página no puede superar 100.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $query = Order::with('user:id,name,email', 'user.customerProfile:id,user_id,first_name,last_name,phone_1,phone_2', 'status', 'locality:id,name')
            ->withCount('items')
            ->latest();

        if ($request->filled('status')) {
            $query->whereHas('status', fn ($q) => $q->where('name', $request->query('status')));
        }

        $page = $query->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'message' => 'Pedidos obtenidos correctamente.',
            'orders' => $page->items(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ], 200);
    }

    #[OA\Patch(
        path: '/api/orders/{id}/cancel',
        summary: 'Cancelar un pedido',
        description: 'El cliente puede cancelar su propio pedido mientras esté pendiente de pago. Un administrador puede cancelar cualquier pedido que todavía no esté enviado, entregado ni cancelado. Devuelve el stock de los productos que salieron del stock.',
        tags: ['Pedidos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del pedido', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pedido cancelado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No podés cancelar este pedido.'),
            new OA\Response(response: 404, description: 'Pedido no encontrado.'),
            new OA\Response(response: 422, description: 'El pedido no se puede cancelar en su estado actual.'),
        ]
    )]
    public function cancel(Request $request, string $id)
    {
        $order = Order::with('status', 'items')->find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Pedido no encontrado.',
            ], 404);
        }

        $user = $request->user();
        $user->loadMissing('userRole.role');
        $isAdmin = in_array($user->userRole?->role?->name, ['SuperAdmin', 'Administrator'], true);

        if ($order->user_id !== $user->id && !$isAdmin) {
            return response()->json([
                'message' => 'No podés cancelar este pedido.',
            ], 403);
        }

        $current = $order->status->name;

        $closed = ['shipped' => 'enviado', 'delivered' => 'entregado', 'cancelled' => 'cancelado'];

        if (isset($closed[$current])) {
            return response()->json([
                'message' => "No se puede cancelar un pedido {$closed[$current]}.",
            ], 422);
        }

        if (!$isAdmin && $current !== 'pending_payment') {
            return response()->json([
                'message' => 'Solo se pueden cancelar pedidos pendientes de pago. Para otros casos, contactá a la empresa.',
            ], 422);
        }

        DB::transaction(function () use ($order, $user, $isAdmin) {
            $this->restoreStock($order);

            $order->changeStatus('cancelled', $user->id, $isAdmin ? 'Cancelado por un administrador.' : 'Cancelado por el cliente.');
        });

        return response()->json([
            'message' => 'Pedido cancelado correctamente.',
            'order' => $order->fresh()->load('status', 'items.product:id,name', 'items.status'),
        ], 200);
    }

    #[OA\Get(
        path: '/api/orders/{id}/tracking',
        summary: 'Seguimiento de un pedido',
        description: 'Devuelve el estado actual, la fecha estimada de envío y el historial de cambios. Lo puede ver su dueño, un administrador o un vendedor con view_all_orders.',
        tags: ['Seguimiento y Envíos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del pedido', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Seguimiento obtenido correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No podés ver el seguimiento de este pedido.'),
            new OA\Response(response: 404, description: 'Pedido no encontrado.'),
        ]
    )]
    public function tracking(Request $request, string $id)
    {
        $order = Order::with('status', 'statusHistory.status', 'statusHistory.changedBy:id,name')->find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Pedido no encontrado.',
            ], 404);
        }

        $viewer = $request->user();

        if ($order->user_id !== $viewer->id && !$this->canViewAll($viewer)) {
            return response()->json([
                'message' => 'No podés ver el seguimiento de este pedido.',
            ], 403);
        }

        return response()->json([
            'message' => 'Seguimiento obtenido correctamente.',
            'tracking' => [
                'order_id' => $order->id,
                'status' => $order->status->name,
                'requires_manufacturing' => $order->requires_manufacturing,
                'estimated_ship_date' => $order->estimated_ship_date?->format('Y-m-d'),
                'shipped_at' => $order->shipped_at,
                'delivered_at' => $order->delivered_at,
                'history' => $order->statusHistory->map(fn ($h) => [
                    'status' => $h->status->name,
                    'changed_by' => $h->changedBy?->name,
                    'note' => $h->note,
                    'date' => $h->created_at,
                ]),
            ],
        ], 200);
    }

    #[OA\Patch(
        path: '/api/orders/{id}/ship-date',
        summary: 'Cambiar la fecha de envío',
        description: 'Cambia la fecha estimada de envío. Tiene que ser lunes o jueves y no puede ser pasada. Solo para pedidos confirmados, en fabricación, terminados o embalados. Requiere el permiso edit_ship_date.',
        tags: ['Seguimiento y Envíos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del pedido', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ship_date'],
                properties: [
                    new OA\Property(property: 'ship_date', type: 'string', format: 'date', example: '2026-11-02'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Fecha de envío actualizada correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Pedido no encontrado.'),
            new OA\Response(response: 422, description: 'Fecha inválida o el pedido no admite cambios.'),
        ]
    )]
    public function updateShipDate(Request $request, string $id)
    {
        $order = Order::with('status')->find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Pedido no encontrado.',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'ship_date' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:today'],
            ], [
                'ship_date.required' => 'La fecha de envío es obligatoria.',
                'ship_date.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
                'ship_date.after_or_equal' => 'La fecha de envío no puede ser pasada.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        if (!in_array($order->status->name, ['confirmed', 'in_production', 'finished', 'packed'], true)) {
            return response()->json([
                'message' => 'Solo se puede cambiar la fecha de envío de pedidos confirmados, en fabricación, terminados o embalados.',
            ], 422);
        }

        $date = Carbon::parse($validated['ship_date']);

        if (!$date->isMonday() && !$date->isThursday()) {
            return response()->json([
                'message' => 'Los envíos salen solo los lunes y los jueves.',
            ], 422);
        }

        DB::transaction(function () use ($order, $date, $request) {
            $order->update(['estimated_ship_date' => $date]);

            $order->statusHistory()->create([
                'order_status_id' => $order->order_status_id,
                'changed_by' => $request->user()->id,
                'note' => 'Fecha de envío cambiada al ' . $date->format('d/m/Y') . '.',
            ]);
        });

        return response()->json([
            'message' => 'Fecha de envío actualizada correctamente.',
            'order' => $order->fresh()->load('status'),
        ], 200);
    }

    private function restoreStock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->from_stock) {
                Product::where('id', $item->product_id)->increment('stock', $item->quantity);
            }
        }
    }

    private function canViewAll($user): bool
    {
        $user->loadMissing('userRole.role', 'userPermission');
        $role = $user->userRole?->role?->name;

        return in_array($role, ['SuperAdmin', 'Administrator'], true)
            || (bool) $user->userPermission?->view_all_orders;
    }

    private function messages(): array
    {
        return [
            'items.required' => 'El pedido debe tener al menos un producto.',
            'items.array' => 'Los productos deben enviarse como una lista.',
            'items.min' => 'El pedido debe tener al menos un producto.',
            'items.max' => 'El pedido no puede tener más de 50 productos distintos.',
            'items.*.product_id.required' => 'Cada producto debe tener su ID.',
            'items.*.product_id.integer' => 'El ID del producto debe ser un número entero.',
            'items.*.product_id.distinct' => 'No se puede repetir el mismo producto; sumá la cantidad.',
            'items.*.product_id.exists' => 'El producto seleccionado no existe o no está activo.',
            'items.*.quantity.required' => 'Cada producto debe tener una cantidad.',
            'items.*.quantity.integer' => 'La cantidad debe ser un número entero.',
            'items.*.quantity.min' => 'La cantidad debe ser al menos 1.',
            'items.*.quantity.max' => 'La cantidad no puede superar las 100 unidades por producto.',
            'locality_id.required' => 'La localidad de entrega es obligatoria.',
            'locality_id.integer' => 'La localidad debe ser un número.',
            'locality_id.exists' => 'La localidad seleccionada no existe o no está activa.',
            'street.required' => 'La calle es obligatoria.',
            'street.max' => 'La calle no puede superar los 120 caracteres.',
            'street.regex' => 'La calle solo puede contener letras, números, espacios, puntos, comas, apóstrofos y guiones.',
            'street_number.required' => 'El número de la calle es obligatorio.',
            'street_number.max' => 'El número no puede superar los 20 caracteres.',
            'street_number.regex' => 'El número solo puede contener letras, números, espacios, barras, puntos y guiones.',
            'address_notes.max' => 'La referencia no puede superar los 255 caracteres.',
        ];
    }
}
