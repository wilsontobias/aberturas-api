<?php

namespace App\Http\Controllers;

use App\Models\AdminActionLog;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    #[OA\Get(
        path: '/api/users',
        summary: 'Listar usuarios',
        description: 'Devuelve los usuarios con su rol, tipo y estado. Se puede filtrar por rol, tipo y estado. Solo administradores.',
        tags: ['Usuarios'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'role', description: 'Filtrar por rol', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['SuperAdmin', 'Administrator', 'OperationalUser'])),
            new OA\Parameter(name: 'type', description: 'Filtrar por tipo de usuario', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['customer', 'seller'])),
            new OA\Parameter(name: 'status', description: '1 para ver solo los activos, 0 para ver solo los desactivados', in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: [0, 1])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Usuarios obtenidos correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 422, description: 'Los filtros no son válidos.'),
        ]
    )]
    public function index(Request $request)
    {
        try {
            $filters = $request->validate([
                'role' => ['sometimes', Rule::in(['SuperAdmin', 'Administrator', 'OperationalUser'])],
                'type' => ['sometimes', Rule::in(['customer', 'seller'])],
                'status' => ['sometimes', Rule::in(['0', '1'])],
            ], [
                'role.in' => 'El rol debe ser SuperAdmin, Administrator u OperationalUser.',
                'type.in' => 'El tipo debe ser customer o seller.',
                'status.in' => 'El estado debe ser 1 (activo) o 0 (desactivado).',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los filtros no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $query = User::with('userRole.role', 'userRole.userType')->orderBy('name');

        if (isset($filters['role'])) {
            $query->whereHas('userRole.role', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        }

        if (isset($filters['type'])) {
            $query->whereHas('userRole.userType', function ($q) use ($filters) {
                $q->where('name', $filters['type']);
            });
        }

        if (isset($filters['status'])) {
            $query->whereHas('userRole', function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            });
        }

        return response()->json([
            'message' => 'Usuarios obtenidos correctamente.',
            'users' => $query->get(),
        ], 200);
    }

    #[OA\Get(
        path: '/api/users/{id}',
        summary: 'Ver un usuario',
        description: 'Devuelve un usuario con su rol, tipo, estado, perfil y permisos. Solo administradores.',
        tags: ['Usuarios'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 2),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Usuario obtenido correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
        ]
    )]
    public function show(string $id)
    {
        $user = User::with(
            'userRole.role',
            'userRole.userType',
            'customerProfile.locality.province',
            'userPermission'
        )->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        return response()->json([
            'message' => 'Usuario obtenido correctamente.',
            'user' => $user,
        ], 200);
    }

    #[OA\Put(
        path: '/api/users/{id}',
        summary: 'Editar un cliente o vendedor',
        description: 'Corrige los datos de un cliente o vendedor. Solo se cambian los campos enviados. Solo administradores.',
        tags: ['Usuarios'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 2),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'first_name', type: 'string', maxLength: 100, example: 'Juan'),
                    new OA\Property(property: 'last_name', type: 'string', maxLength: 100, example: 'Pérez'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'juan@ejemplo.com'),
                    new OA\Property(property: 'locality_id', type: 'integer', example: 2),
                    new OA\Property(property: 'phone_1', type: 'string', maxLength: 30, example: '3454111111'),
                    new OA\Property(property: 'phone_2', type: 'string', maxLength: 30, example: '3454222222'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Usuario actualizado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $user = User::with('userRole.role', 'customerProfile')->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        if ($user->userRole->role->name !== 'OperationalUser') {
            return response()->json([
                'message' => 'Desde acá solo se pueden editar clientes y vendedores.',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'first_name' => ['sometimes', 'string', 'max:100'],
                'last_name' => ['sometimes', 'string', 'max:100'],
                'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
                'locality_id' => ['sometimes', 'integer', Rule::exists('localities', 'id')->where('status', true)],
                'phone_1' => ['sometimes', 'string', 'max:30'],
                'phone_2' => ['sometimes', 'string', 'max:30'],
            ], [
                'first_name.max' => 'El nombre no puede superar los 100 caracteres.',
                'last_name.max' => 'El apellido no puede superar los 100 caracteres.',
                'email.email' => 'El correo electrónico no es válido.',
                'email.unique' => 'El correo electrónico ya está registrado.',
                'locality_id.integer' => 'La localidad debe ser un número.',
                'locality_id.exists' => 'La localidad seleccionada no existe o no está activa.',
                'phone_1.max' => 'El teléfono 1 no puede superar los 30 caracteres.',
                'phone_2.max' => 'El teléfono 2 no puede superar los 30 caracteres.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        if (empty($validated)) {
            return response()->json([
                'message' => 'No se envió ningún dato para actualizar.',
            ], 422);
        }

        DB::transaction(function () use ($user, $validated) {
            $profile = $user->customerProfile;

            $profile->update(collect($validated)->except('email')->all());

            $userData = ['name' => $profile->first_name . ' ' . $profile->last_name];

            if (isset($validated['email'])) {
                $userData['email'] = $validated['email'];
            }

            $user->update($userData);
        });

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'user' => $user->fresh()->load('userRole.role', 'userRole.userType', 'customerProfile.locality'),
        ], 200);
    }

    #[OA\Patch(
        path: '/api/users/{id}/status',
        summary: 'Activar o desactivar un cliente o vendedor',
        description: 'Cambia el estado de un cliente o vendedor. Un usuario desactivado no se borra, pero no puede iniciar sesión. Solo administradores.',
        tags: ['Usuarios'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 2),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'boolean', example: false),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Estado actualizado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function updateStatus(Request $request, string $id)
    {
        $user = User::with('userRole.role')->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        if ($user->userRole->role->name !== 'OperationalUser') {
            return response()->json([
                'message' => 'Desde acá solo se pueden activar o desactivar clientes y vendedores.',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'status' => ['required', 'boolean'],
            ], [
                'status.required' => 'El estado es obligatorio.',
                'status.boolean' => 'El estado debe ser verdadero o falso.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $newStatus = (bool) $validated['status'];

        if ($user->userRole->status === $newStatus) {
            return response()->json([
                'message' => $newStatus ? 'El usuario ya está activo.' : 'El usuario ya está desactivado.',
            ], 422);
        }

        DB::transaction(function () use ($request, $user, $newStatus) {
            $user->userRole->update(['status' => $newStatus]);

            if (!$newStatus) {
                $user->tokens()->delete();
            }

            AdminActionLog::record(
                $newStatus ? 'activate_user' : 'deactivate_user',
                $request->user()->id,
                $user->id
            );
        });

        return response()->json([
            'message' => $newStatus ? 'Usuario activado correctamente.' : 'Usuario desactivado correctamente.',
            'user' => $user->fresh()->load('userRole.role', 'userRole.userType'),
        ], 200);
    }

    #[OA\Patch(
        path: '/api/users/{id}/type',
        summary: 'Convertir un cliente en vendedor o al revés',
        description: 'Cambia el tipo de un usuario operativo y le carga los permisos por defecto del nuevo tipo. Nadie puede cambiarse el tipo a sí mismo. Solo administradores.',
        tags: ['Usuarios'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 2),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['customer', 'seller'], example: 'seller'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Tipo de usuario actualizado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function changeType(Request $request, string $id)
    {
        $user = User::with('userRole.role', 'userRole.userType')->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'No podés cambiarte el tipo a vos mismo.',
            ], 403);
        }

        if ($user->userRole->role->name !== 'OperationalUser') {
            return response()->json([
                'message' => 'Solo los clientes y vendedores tienen tipo de usuario.',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'type' => ['required', Rule::in(['customer', 'seller'])],
            ], [
                'type.required' => 'El tipo es obligatorio.',
                'type.in' => 'El tipo debe ser customer o seller.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $oldType = $user->userRole->userType->name;
        $newType = $validated['type'];

        if ($oldType === $newType) {
            return response()->json([
                'message' => 'El usuario ya es de ese tipo.',
            ], 422);
        }

        DB::transaction(function () use ($request, $user, $oldType, $newType) {
            $user->userRole->update([
                'user_type_id' => UserType::where('name', $newType)->value('id'),
            ]);

            $user->userPermission()->updateOrCreate(
                [],
                UserPermission::defaultsFor($newType)
            );

            AdminActionLog::record(
                'change_user_type',
                $request->user()->id,
                $user->id,
                $oldType . ' -> ' . $newType
            );
        });

        return response()->json([
            'message' => 'Tipo de usuario actualizado correctamente.',
            'user' => $user->fresh()->load('userRole.role', 'userRole.userType', 'userPermission'),
        ], 200);
    }
}