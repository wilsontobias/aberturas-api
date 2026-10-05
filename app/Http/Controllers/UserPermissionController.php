<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class UserPermissionController extends Controller
{
    #[OA\Get(
        path: '/api/users/{id}/permissions',
        summary: 'Ver los permisos de un usuario',
        description: 'Devuelve los permisos individuales de un usuario operativo (cliente o vendedor). Solo administradores.',
        tags: ['Permisos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 2),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Permisos obtenidos correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 422, description: 'El usuario no es un usuario operativo.'),
        ]
    )]
    public function show(string $id)
    {
        $user = User::with('userRole.role', 'userRole.userType')->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        if ($user->userRole->role->name !== 'OperationalUser') {
            return response()->json([
                'message' => 'Solo los clientes y vendedores tienen permisos individuales.',
            ], 422);
        }

        $permissions = $user->userPermission()->firstOrCreate(
            [],
            UserPermission::defaultsFor($user->userRole->userType->name)
        );

        return response()->json([
            'message' => 'Permisos obtenidos correctamente.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'type' => $user->userRole->userType->name,
            ],
            'permissions' => $permissions->fresh(),
        ], 200);
    }

    #[OA\Put(
        path: '/api/users/{id}/permissions',
        summary: 'Cambiar los permisos de un usuario',
        description: 'Activa o desactiva permisos de un usuario operativo. Solo se cambian los permisos enviados. Solo administradores.',
        tags: ['Permisos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 2),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'create_orders', type: 'boolean', example: true),
                    new OA\Property(property: 'view_own_orders', type: 'boolean', example: true),
                    new OA\Property(property: 'view_all_orders', type: 'boolean', example: false),
                    new OA\Property(property: 'verify_payments', type: 'boolean', example: false),
                    new OA\Property(property: 'update_item_status', type: 'boolean', example: false),
                    new OA\Property(property: 'edit_ship_date', type: 'boolean', example: false),
                    new OA\Property(property: 'register_balance_payment', type: 'boolean', example: false),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Permisos actualizados correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $user = User::with('userRole.role', 'userRole.userType')->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        if ($user->userRole->role->name !== 'OperationalUser') {
            return response()->json([
                'message' => 'Solo los clientes y vendedores tienen permisos individuales.',
            ], 422);
        }

        try {
            $validated = $request->validate([
                'create_orders' => ['sometimes', 'boolean'],
                'view_own_orders' => ['sometimes', 'boolean'],
                'view_all_orders' => ['sometimes', 'boolean'],
                'verify_payments' => ['sometimes', 'boolean'],
                'update_item_status' => ['sometimes', 'boolean'],
                'edit_ship_date' => ['sometimes', 'boolean'],
                'register_balance_payment' => ['sometimes', 'boolean'],
            ], [
                'boolean' => 'Cada permiso debe ser verdadero o falso.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $permissions = $user->userPermission()->firstOrCreate(
            [],
            UserPermission::defaultsFor($user->userRole->userType->name)
        );

        $permissions->update($validated);

        return response()->json([
            'message' => 'Permisos actualizados correctamente.',
            'permissions' => $permissions->fresh(),
        ], 200);
    }
}