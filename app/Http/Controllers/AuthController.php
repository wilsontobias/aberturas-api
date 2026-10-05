<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/register',
        summary: 'Registrarse como cliente',
        description: 'Crea un usuario nuevo con rol de usuario operativo y tipo cliente, y devuelve un token de acceso.',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['first_name', 'last_name', 'email', 'password', 'locality_id', 'phone_1', 'phone_2'],
                properties: [
                    new OA\Property(property: 'first_name', type: 'string', maxLength: 100, example: 'Juan'),
                    new OA\Property(property: 'last_name', type: 'string', maxLength: 100, example: 'Pérez'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'juan@ejemplo.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'Cliente123'),
                    new OA\Property(property: 'locality_id', type: 'integer', example: 2),
                    new OA\Property(property: 'phone_1', type: 'string', maxLength: 30, example: '3454111111'),
                    new OA\Property(property: 'phone_2', type: 'string', maxLength: 30, example: '3454222222'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Usuario registrado correctamente.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
                'locality_id' => [
                    'required',
                    'integer',
                    Rule::exists('localities', 'id')->where('status', true),
                ],
                'phone_1' => ['required', 'string', 'max:30'],
                'phone_2' => ['required', 'string', 'max:30'],
            ], [
                'first_name.required' => 'El nombre es obligatorio.',
                'first_name.max' => 'El nombre no puede superar los 100 caracteres.',
                'last_name.required' => 'El apellido es obligatorio.',
                'last_name.max' => 'El apellido no puede superar los 100 caracteres.',
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'El correo electrónico no es válido.',
                'email.unique' => 'El correo electrónico ya está registrado.',
                'password.required' => 'La contraseña es obligatoria.',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
                'locality_id.required' => 'La localidad es obligatoria.',
                'locality_id.integer' => 'La localidad debe ser un número.',
                'locality_id.exists' => 'La localidad seleccionada no existe o no está activa.',
                'phone_1.required' => 'El teléfono 1 es obligatorio.',
                'phone_1.max' => 'El teléfono 1 no puede superar los 30 caracteres.',
                'phone_2.required' => 'El teléfono 2 es obligatorio.',
                'phone_2.max' => 'El teléfono 2 no puede superar los 30 caracteres.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['first_name'] . ' ' . $validated['last_name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $user->userRole()->create([
                'role_id' => Role::where('name', 'OperationalUser')->value('id'),
                'user_type_id' => UserType::where('name', 'customer')->value('id'),
                'status' => true,
                'is_owner' => false,
            ]);

            $user->customerProfile()->create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'locality_id' => $validated['locality_id'],
                'phone_1' => $validated['phone_1'],
                'phone_2' => $validated['phone_2'],
            ]);

            $user->userPermission()->create(UserPermission::defaultsFor('customer'));

            return $user;
        });

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Usuario registrado correctamente.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('userRole.role', 'userRole.userType', 'customerProfile.locality'),
        ], 201);
    }

    #[OA\Post(
        path: '/api/login',
        summary: 'Iniciar sesión',
        description: 'Verifica email y contraseña y devuelve un token de acceso de Sanctum.',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'superadmin@aberturas.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'SuperAdmin123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login exitoso.'),
            new OA\Response(response: 401, description: 'Las credenciales proporcionadas son incorrectas.'),
            new OA\Response(response: 403, description: 'El usuario está desactivado.'),
            new OA\Response(response: 422, description: 'Los datos proporcionados no son válidos.'),
        ]
    )]
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => ['required', 'string', 'email'],
                'password' => ['required', 'string'],
            ], [
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'El correo electrónico no es válido.',
                'password.required' => 'La contraseña es obligatoria.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Las credenciales proporcionadas son incorrectas.',
            ], 401);
        }

        if (!$user->userRole || !$user->userRole->status) {
            return response()->json([
                'message' => 'Tu usuario está desactivado. Comunicate con un administrador.',
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('userRole.role', 'userRole.userType'),
        ], 200);
    }

    #[OA\Get(
        path: '/api/me',
        summary: 'Ver mis datos',
        description: 'Devuelve los datos del usuario dueño del token, con su rol, tipo y perfil.',
        tags: ['Autenticación'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Datos del usuario obtenidos correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
        ]
    )]
    public function me(Request $request)
    {
        $user = $request->user()->load(
            'userRole.role',
            'userRole.userType',
            'customerProfile.locality.province',
            'userPermission'
        );

        return response()->json([
            'message' => 'Datos del usuario obtenidos correctamente.',
            'user' => $user,
        ], 200);
    }

    #[OA\Post(
        path: '/api/logout',
        summary: 'Cerrar sesión',
        description: 'Elimina el token con el que se hizo el pedido. Después de esto, ese token deja de servir.',
        tags: ['Autenticación'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sesión cerrada correctamente.'),
            new OA\Response(response: 401, description: 'No estás autenticado.'),
        ]
    )]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ], 200);
    }
}