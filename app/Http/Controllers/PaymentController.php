<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Payment;
use App\Models\PaymentStatus;
use App\Models\PaymentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class PaymentController extends Controller
{
    #[OA\Post(
        path: '/api/orders/{id}/payments',
        summary: 'Subir comprobante de la seña',
        description: 'El dueño del pedido sube el comprobante de la seña, solo si el pedido está pendiente de pago. El monto lo calcula el sistema y el pedido pasa a comprobante en revisión. Archivo JPG, PNG o PDF de hasta 2 MB.',
        tags: ['Pagos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del pedido', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['receipt'],
                    properties: [
                        new OA\Property(property: 'receipt', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Comprobante subido correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'Solo podés pagar tus propios pedidos.'),
            new OA\Response(response: 404, description: 'Pedido no encontrado.'),
            new OA\Response(response: 422, description: 'Datos inválidos o el pago no corresponde.'),
        ]
    )]
    public function store(Request $request, string $id)
    {
        $order = Order::with('status', 'payments.type', 'payments.status')->find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Pedido no encontrado.',
            ], 404);
        }

        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Solo podés pagar tus propios pedidos.',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'receipt' => ['bail', 'required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
            ], [
                'receipt.required' => 'El comprobante es obligatorio.',
                'receipt.file' => 'El comprobante debe ser un archivo.',
                'receipt.uploaded' => 'No se pudo subir el comprobante. Probá con un archivo de hasta 2 MB.',
                'receipt.mimes' => 'El comprobante debe ser JPG, PNG o PDF.',
                'receipt.max' => 'El comprobante no puede superar los 2 MB.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        if ($order->status->name !== 'pending_payment') {
            return response()->json([
                'message' => 'Solo se puede subir el comprobante de la seña mientras el pedido está pendiente de pago.',
            ], 422);
        }

        $path = $request->file('receipt')->store("receipts/order-{$order->id}", 'local');

        try {
            $payment = DB::transaction(function () use ($order, $path, $request) {
                $payment = Payment::create([
                    'order_id' => $order->id,
                    'payment_type_id' => PaymentType::where('name', 'deposit')->value('id'),
                    'payment_status_id' => PaymentStatus::where('name', 'pending')->value('id'),
                    'amount' => $order->deposit_amount,
                    'receipt_path' => $path,
                    'uploaded_by' => $request->user()->id,
                ]);

                $order->changeStatus('receipt_in_review', $request->user()->id, 'Comprobante de la seña subido.');

                return $payment;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return response()->json([
            'message' => 'Comprobante subido correctamente. Queda pendiente de revisión.',
            'payment' => $payment->load('type', 'status'),
            'order_status' => 'receipt_in_review',
        ], 201);
    }

    #[OA\Get(
        path: '/api/orders/{id}/payments',
        summary: 'Ver los pagos de un pedido',
        description: 'Lista los pagos de un pedido. Lo puede ver su dueño, un administrador o un vendedor con view_all_orders o verify_payments.',
        tags: ['Pagos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del pedido', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Pagos obtenidos correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No podés ver los pagos de este pedido.'),
            new OA\Response(response: 404, description: 'Pedido no encontrado.'),
        ]
    )]
    public function index(Request $request, string $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Pedido no encontrado.',
            ], 404);
        }

        $user = $request->user();

        if ($order->user_id !== $user->id && !$this->canReview($user)) {
            return response()->json([
                'message' => 'No podés ver los pagos de este pedido.',
            ], 403);
        }

        $payments = $order->payments()
            ->with('type', 'status', 'uploader:id,name', 'verifier:id,name')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Pagos obtenidos correctamente.',
            'payments' => $payments,
        ], 200);
    }

    #[OA\Get(
        path: '/api/payments/{id}/receipt',
        summary: 'Descargar comprobante',
        description: 'Descarga el archivo del comprobante. Lo puede descargar el dueño del pedido, un administrador o un vendedor con view_all_orders o verify_payments.',
        tags: ['Pagos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del pago', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo del comprobante.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No podés ver este comprobante.'),
            new OA\Response(response: 404, description: 'Pago o comprobante no encontrado.'),
        ]
    )]
    public function receipt(Request $request, string $id)
    {
        $payment = Payment::with('order')->find($id);

        if (!$payment) {
            return response()->json([
                'message' => 'Pago no encontrado.',
            ], 404);
        }

        $user = $request->user();

        if ($payment->order->user_id !== $user->id && !$this->canReview($user)) {
            return response()->json([
                'message' => 'No podés ver este comprobante.',
            ], 403);
        }

        if (!$payment->receipt_path || !Storage::disk('local')->exists($payment->receipt_path)) {
            return response()->json([
                'message' => 'Comprobante no encontrado.',
            ], 404);
        }

        return Storage::disk('local')->download($payment->receipt_path);
    }

    #[OA\Patch(
        path: '/api/payments/{id}/verify',
        summary: 'Aprobar o rechazar un pago',
        description: 'Aprueba o rechaza el comprobante de una seña. Si se aprueba, el pedido pasa a confirmado, se calcula la fecha de envío y, si hay que fabricar, empieza la fabricación; si se rechaza, el motivo es obligatorio y el pedido vuelve a pendiente de pago. Requiere el permiso verify_payments.',
        tags: ['Pagos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del pago', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['approved'],
                properties: [
                    new OA\Property(property: 'approved', type: 'boolean', example: true),
                    new OA\Property(property: 'rejection_reason', type: 'string', nullable: true, example: 'El monto no coincide con la seña.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pago verificado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Pago no encontrado.'),
            new OA\Response(response: 422, description: 'Datos inválidos o el pago ya fue revisado.'),
        ]
    )]
    public function verify(Request $request, string $id)
    {
        $payment = Payment::with('type', 'status', 'order.status')->find($id);

        if (!$payment) {
            return response()->json([
                'message' => 'Pago no encontrado.',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'approved' => ['required', 'boolean'],
                'rejection_reason' => ['required_if:approved,false,0', 'nullable', 'string', 'max:255'],
            ], [
                'approved.required' => 'Indicá si el pago se aprueba o se rechaza.',
                'approved.boolean' => 'El campo approved debe ser verdadero o falso.',
                'rejection_reason.required_if' => 'Si rechazás el pago, tenés que indicar el motivo.',
                'rejection_reason.max' => 'El motivo no puede superar los 255 caracteres.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        if ($payment->status->name !== 'pending') {
            return response()->json([
                'message' => 'Este pago ya fue revisado.',
            ], 422);
        }

        if ($payment->order->status->name !== 'receipt_in_review') {
            return response()->json([
                'message' => 'El pedido ya no está esperando la revisión de este comprobante.',
            ], 422);
        }

        $approved = (bool) $validated['approved'];

        DB::transaction(function () use ($payment, $approved, $validated, $request) {
            $payment->update([
                'payment_status_id' => PaymentStatus::where('name', $approved ? 'approved' : 'rejected')->value('id'),
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
                'rejection_reason' => $approved ? null : $validated['rejection_reason'],
            ]);

            if ($payment->type->name === 'deposit') {
                if ($approved) {
                    $payment->order->changeStatus('confirmed', $request->user()->id, 'Seña aprobada.');
                    $payment->order->startProduction($request->user()->id);
                } else {
                    $payment->order->changeStatus('pending_payment', $request->user()->id, 'Comprobante rechazado: ' . $validated['rejection_reason']);
                }
            }
        });

        return response()->json([
            'message' => $approved ? 'Pago aprobado correctamente.' : 'Pago rechazado.',
            'payment' => $payment->fresh()->load('type', 'status', 'verifier:id,name', 'order.status'),
        ], 200);
    }

    #[OA\Post(
        path: '/api/orders/{id}/payments/cash',
        summary: 'Registrar el pago del saldo',
        description: 'El vendedor registra que el cliente pagó el saldo al recibir el pedido. El pedido tiene que estar enviado; el pago queda aprobado directamente, sin comprobante, y el pedido pasa a entregado. Requiere el permiso register_balance_payment.',
        tags: ['Pagos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del pedido', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Pago en efectivo registrado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Pedido no encontrado.'),
            new OA\Response(response: 422, description: 'El pago no corresponde.'),
        ]
    )]
    public function registerCashBalance(Request $request, string $id)
    {
        $order = Order::with('status', 'payments.type', 'payments.status')->find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Pedido no encontrado.',
            ], 404);
        }

        if ($order->status->name !== 'shipped') {
            return response()->json([
                'message' => 'El saldo se registra cuando el pedido está enviado, al momento de entregarlo.',
            ], 422);
        }

        $depositApproved = $order->payments->first(
            fn ($p) => $p->type->name === 'deposit' && $p->status->name === 'approved'
        );

        if (!$depositApproved) {
            return response()->json([
                'message' => 'Primero tiene que estar aprobada la seña.',
            ], 422);
        }

        $balanceActive = $order->payments->first(
            fn ($p) => $p->type->name === 'balance' && in_array($p->status->name, ['pending', 'approved'], true)
        );

        if ($balanceActive) {
            return response()->json([
                'message' => 'Ya hay un pago de saldo registrado para este pedido.',
            ], 422);
        }

        $payment = DB::transaction(function () use ($order, $request) {
            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_type_id' => PaymentType::where('name', 'balance')->value('id'),
                'payment_status_id' => PaymentStatus::where('name', 'approved')->value('id'),
                'amount' => $order->balance_amount,
                'receipt_path' => null,
                'uploaded_by' => $request->user()->id,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
            ]);

            $order->changeStatus('delivered', $request->user()->id, 'Saldo pagado. Pedido entregado.');

            return $payment;
        });

        return response()->json([
            'message' => 'Pago del saldo registrado correctamente. El pedido quedó entregado.',
            'payment' => $payment->load('type', 'status', 'verifier:id,name'),
        ], 201);
    }

    private function canReview($user): bool
    {
        $user->loadMissing('userRole.role', 'userPermission');
        $role = $user->userRole?->role?->name;

        return in_array($role, ['SuperAdmin', 'Administrator'], true)
            || (bool) $user->userPermission?->view_all_orders
            || (bool) $user->userPermission?->verify_payments;
    }
}
