<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    #[OA\Put(
        path: '/api/me/profile',
        summary: 'Completar o actualizar mi perfil de cliente',
        description: 'Guarda nombre, apellido, localidad y teléfonos del cliente. Lo usan los clientes que entraron con Google y todavía no tienen perfil (sin perfil no se pueden crear pedidos).',
        tags: ['Autenticación'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['first_name', 'last_name', 'locality_id', 'phone_1', 'phone_2'],
                properties: [
                    new OA\Property(property: 'first_name', type: 'string', example: 'María'),
                    new OA\Property(property: 'last_name', type: 'string', example: 'Pérez'),
                    new OA\Property(property: 'locality_id', type: 'integer', example: 1),
                    new OA\Property(property: 'phone_1', type: 'string', example: '3454123456'),
                    new OA\Property(property: 'phone_2', type: 'string', example: '3454654321'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Perfil guardado correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
            new OA\Response(response: 403, description: 'Solo los clientes tienen perfil.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function update(Request $request)
    {
        $user = $request->user()->load('userRole.userType');

        if ($user->userRole?->userType?->name !== 'customer') {
            return response()->json([
                'message' => 'Solo los clientes tienen perfil.',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'first_name' => ['required', 'string', 'max:100', 'regex:/^[\pL\s\'-]+$/u'],
                'last_name' => ['required', 'string', 'max:100', 'regex:/^[\pL\s\'-]+$/u'],
                'locality_id' => ['required', 'integer', Rule::exists('localities', 'id')->where('status', true)],
                'phone_1' => ['required', 'string', 'max:30', 'regex:/^[0-9\s()+-]+$/'],
                'phone_2' => ['required', 'string', 'max:30', 'regex:/^[0-9\s()+-]+$/'],
            ], [
                'first_name.required' => 'El nombre es obligatorio.',
                'first_name.regex' => 'El nombre solo puede contener letras, espacios, apóstrofos y guiones.',
                'first_name.max' => 'El nombre no puede superar los 100 caracteres.',
                'last_name.required' => 'El apellido es obligatorio.',
                'last_name.regex' => 'El apellido solo puede contener letras, espacios, apóstrofos y guiones.',
                'last_name.max' => 'El apellido no puede superar los 100 caracteres.',
                'locality_id.required' => 'La localidad es obligatoria.',
                'locality_id.integer' => 'La localidad debe ser un número.',
                'locality_id.exists' => 'La localidad seleccionada no existe o no está activa.',
                'phone_1.required' => 'El teléfono 1 es obligatorio.',
                'phone_1.regex' => 'El teléfono 1 solo puede contener números, espacios, paréntesis, + y -.',
                'phone_1.max' => 'El teléfono 1 no puede superar los 30 caracteres.',
                'phone_2.required' => 'El teléfono 2 es obligatorio.',
                'phone_2.regex' => 'El teléfono 2 solo puede contener números, espacios, paréntesis, + y -.',
                'phone_2.max' => 'El teléfono 2 no puede superar los 30 caracteres.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        DB::transaction(function () use ($user, $validated) {
            $user->customerProfile()->updateOrCreate(['user_id' => $user->id], $validated);
            $user->update(['name' => $validated['first_name'] . ' ' . $validated['last_name']]);
        });

        return response()->json([
            'message' => 'Perfil guardado correctamente.',
            'user' => $user->fresh()->load('userRole.role', 'userRole.userType', 'userPermission', 'customerProfile.locality'),
        ], 200);
    }
}
