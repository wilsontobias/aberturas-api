<?php

namespace App\Http\Controllers;

use App\Models\AdminActionLog;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class AdminRoleController extends Controller
{
    #[OA\Put(
        path: '/api/users/{id}/admin',
        summary: 'Dar el rol de Administrador',
        description: 'Convierte a un cliente o vendedor activo en Administrador. Pueden hacerlo el Super Administrador y los Administradores Propietarios. Queda registrado en admin_action_logs.',
        tags: ['Administradores'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 2),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rol de Administrador otorgado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 422, description: 'El usuario no puede recibir ese rol.'),
        ]
    )]
    public function grantAdmin(Request $request, string $id)
    {
        $actor = $this->actor($request);

        if (!$this->isSuper($actor) && !$this->isOwner($actor)) {
            return $this->forbidden('Solo el Super Administrador o un Administrador Propietario pueden dar el rol de Administrador.');
        }

        $user = User::with('userRole.role')->find($id);

        if (!$user) {
            return $this->notFound();
        }

        if ($user->userRole->role->name !== 'OperationalUser') {
            return $this->invalid('El usuario ya es administrador.');
        }

        if (!$user->userRole->status) {
            return $this->invalid('El usuario está desactivado. Activalo antes de darle el rol de Administrador.');
        }

        DB::transaction(function () use ($actor, $user) {
            $user->userRole->update([
                'role_id' => Role::where('name', 'Administrator')->value('id'),
                'user_type_id' => null,
                'is_owner' => false,
            ]);

            AdminActionLog::record('grant_admin', $actor->id, $user->id);
        });

        return $this->done('Rol de Administrador otorgado correctamente.', $user);
    }

    #[OA\Delete(
        path: '/api/users/{id}/admin',
        summary: 'Quitar el rol de Administrador',
        description: 'Convierte a un Administrador en cliente, con los permisos por defecto de un cliente. Solo el Super Administrador. Si es Propietario, primero hay que quitarle esa condición. Queda registrado en admin_action_logs.',
        tags: ['Administradores'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 2),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rol de Administrador quitado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 422, description: 'El usuario no es administrador o es propietario.'),
        ]
    )]
    public function revokeAdmin(Request $request, string $id)
    {
        $actor = $this->actor($request);

        if (!$this->isSuper($actor)) {
            return $this->forbidden('Solo el Super Administrador puede quitar el rol de Administrador.');
        }

        $user = User::with('userRole.role')->find($id);

        if (!$user) {
            return $this->notFound();
        }

        if ($user->userRole->role->name !== 'Administrator') {
            return $this->invalid('El usuario no es administrador.');
        }

        if ($user->userRole->is_owner) {
            return $this->invalid('Primero quitale el atributo de Administrador Propietario.');
        }

        DB::transaction(function () use ($actor, $user) {
            $user->userRole->update([
                'role_id' => Role::where('name', 'OperationalUser')->value('id'),
                'user_type_id' => UserType::where('name', 'customer')->value('id'),
            ]);

            $user->userPermission()->updateOrCreate([], UserPermission::defaultsFor('customer'));

            AdminActionLog::record('revoke_admin', $actor->id, $user->id);
        });

        return $this->done('Rol de Administrador quitado correctamente. El usuario vuelve a ser cliente.', $user);
    }

    #[OA\Put(
        path: '/api/users/{id}/owner',
        summary: 'Dar el atributo de Administrador Propietario',
        description: 'Marca a un Administrador activo como Propietario: solo el Super Administrador podrá desactivarlo. Solo el Super Administrador. Queda registrado en admin_action_logs.',
        tags: ['Administradores'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 2),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Administrador Propietario otorgado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 422, description: 'El usuario no puede ser propietario.'),
        ]
    )]
    public function grantOwner(Request $request, string $id)
    {
        $actor = $this->actor($request);

        if (!$this->isSuper($actor)) {
            return $this->forbidden('Solo el Super Administrador puede dar el atributo de Administrador Propietario.');
        }

        $user = User::with('userRole.role')->find($id);

        if (!$user) {
            return $this->notFound();
        }

        if ($user->userRole->role->name !== 'Administrator') {
            return $this->invalid('Solo un Administrador puede ser Propietario. Primero dale el rol de Administrador.');
        }

        if (!$user->userRole->status) {
            return $this->invalid('El administrador está desactivado.');
        }

        if ($user->userRole->is_owner) {
            return $this->invalid('El administrador ya es Propietario.');
        }

        DB::transaction(function () use ($actor, $user) {
            $user->userRole->update(['is_owner' => true]);

            AdminActionLog::record('grant_owner', $actor->id, $user->id);
        });

        return $this->done('Administrador Propietario otorgado correctamente.', $user);
    }

    #[OA\Delete(
        path: '/api/users/{id}/owner',
        summary: 'Quitar el atributo de Administrador Propietario',
        description: 'Deja de proteger a un Administrador: desde ese momento pueden desactivarlo los Propietarios y el Super Administrador. Solo el Super Administrador. Queda registrado en admin_action_logs.',
        tags: ['Administradores'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 2),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Atributo de Propietario quitado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para realizar esta acción.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 422, description: 'El usuario no es propietario.'),
        ]
    )]
    public function revokeOwner(Request $request, string $id)
    {
        $actor = $this->actor($request);

        if (!$this->isSuper($actor)) {
            return $this->forbidden('Solo el Super Administrador puede quitar el atributo de Administrador Propietario.');
        }

        $user = User::with('userRole.role')->find($id);

        if (!$user) {
            return $this->notFound();
        }

        if (!$user->userRole->is_owner) {
            return $this->invalid('El administrador no es Propietario.');
        }

        DB::transaction(function () use ($actor, $user) {
            $user->userRole->update(['is_owner' => false]);

            AdminActionLog::record('revoke_owner', $actor->id, $user->id);
        });

        return $this->done('Atributo de Administrador Propietario quitado correctamente.', $user);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        $user->loadMissing('userRole.role');

        return $user;
    }

    private function isSuper(User $user): bool
    {
        return $user->userRole->role->name === 'SuperAdmin';
    }

    private function isOwner(User $user): bool
    {
        return $user->userRole->role->name === 'Administrator' && $user->userRole->is_owner;
    }

    private function forbidden(string $message)
    {
        return response()->json(['message' => $message], 403);
    }

    private function notFound()
    {
        return response()->json(['message' => 'Usuario no encontrado.'], 404);
    }

    private function invalid(string $message)
    {
        return response()->json(['message' => $message], 422);
    }

    private function done(string $message, User $user)
    {
        return response()->json([
            'message' => $message,
            'user' => $user->fresh()->load('userRole.role', 'userRole.userType', 'userPermission'),
        ], 200);
    }
}
