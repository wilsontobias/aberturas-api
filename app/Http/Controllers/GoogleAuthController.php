<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use OpenApi\Attributes as OA;

/**
 * Inicio de sesión con Google (OAuth 2.0 con Laravel Socialite).
 *
 * Recorrido: el front manda al usuario a /redirect → Google → vuelve a /callback →
 * la API crea o busca al usuario y lo manda de vuelta al front con un código de un solo uso →
 * el front cambia ese código por el token de Sanctum llamando a /exchange.
 * Así el token nunca viaja en la dirección del navegador.
 */
class GoogleAuthController extends Controller
{
    #[OA\Get(
        path: '/api/auth/google/redirect',
        summary: 'Ingresar con Google (paso 1)',
        description: 'Redirige al usuario a la pantalla de Google para que elija su cuenta. Se abre desde el navegador, no desde Swagger.',
        tags: ['Autenticación'],
        responses: [
            new OA\Response(response: 302, description: 'Redirección a Google.'),
        ]
    )]
    public function redirect()
    {
        if (!config('services.google.client_id') || !config('services.google.client_secret')) {
            return $this->backToFront('/login', ['google_error' => 'not_configured']);
        }

        // "state" evita que alguien nos haga volver de Google con un pedido que no iniciamos nosotros.
        $state = Str::random(40);
        Cache::put('google_state:' . $state, true, now()->addMinutes(10));

        return Socialite::driver('google')
            ->stateless()
            ->with(['state' => $state, 'prompt' => 'select_account'])
            ->redirect();
    }

    #[OA\Get(
        path: '/api/auth/google/callback',
        summary: 'Ingresar con Google (paso 2)',
        description: 'Es la dirección a la que vuelve Google. Crea o busca al usuario por su email y redirige al front con un código de un solo uso. No se llama a mano.',
        tags: ['Autenticación'],
        responses: [
            new OA\Response(response: 302, description: 'Redirección al front.'),
        ]
    )]
    public function callback(Request $request)
    {
        if ($request->filled('error')) {
            return $this->backToFront('/login', ['google_error' => 'cancelled']);
        }

        if (!Cache::pull('google_state:' . $request->query('state', ''))) {
            return $this->backToFront('/login', ['google_error' => 'state']);
        }

        try {
            $google = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            Log::warning('Falló el ingreso con Google: ' . $e->getMessage());

            return $this->backToFront('/login', ['google_error' => 'failed']);
        }

        $email = $google->getEmail();
        $verified = filter_var($google->user['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$email || !$verified) {
            return $this->backToFront('/login', ['google_error' => 'email']);
        }

        $user = User::with('userRole')->where('email', $email)->first();

        if ($user && (!$user->userRole || !$user->userRole->status)) {
            return $this->backToFront('/login', ['google_error' => 'inactive']);
        }

        if (!$user) {
            $user = $this->createCustomer($email, $google->getName() ?: Str::before($email, '@'));
        }

        $code = Str::random(48);
        Cache::put('google_code:' . hash('sha256', $code), $user->id, now()->addSeconds(60));

        return $this->backToFront('/auth/google', ['code' => $code]);
    }

    #[OA\Post(
        path: '/api/auth/google/exchange',
        summary: 'Ingresar con Google (paso 3)',
        description: 'El front cambia el código de un solo uso (vale 60 segundos) por el token de acceso de Sanctum. Devuelve lo mismo que el login.',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', example: 'abc123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login exitoso.'),
            new OA\Response(response: 403, description: 'Usuario desactivado.'),
            new OA\Response(response: 422, description: 'Código inválido, vencido o ya usado.'),
        ]
    )]
    public function exchange(Request $request)
    {
        try {
            $validated = $request->validate([
                'code' => ['required', 'string', 'max:100'],
            ], [
                'code.required' => 'Falta el código de ingreso.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        $userId = Cache::pull('google_code:' . hash('sha256', $validated['code']));
        $user = $userId ? User::with('userRole')->find($userId) : null;

        if (!$user) {
            return response()->json([
                'message' => 'El código de ingreso venció o ya se usó. Probá de nuevo.',
            ], 422);
        }

        if (!$user->userRole || !$user->userRole->status) {
            return response()->json([
                'message' => 'Tu usuario está desactivado. Comunicate con un administrador.',
            ], 403);
        }

        return response()->json([
            'message' => 'Login exitoso.',
            'access_token' => $user->createToken('auth_token')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user->load('userRole.role', 'userRole.userType', 'customerProfile.locality'),
        ], 200);
    }

    /** Cliente nuevo: sin perfil todavía (después completa sus datos desde el front). */
    private function createCustomer(string $email, string $name): User
    {
        return DB::transaction(function () use ($email, $name) {
            $user = User::create([
                'name' => mb_substr($name, 0, 255),
                'email' => $email,
                'password' => Str::random(40),
            ]);

            $user->userRole()->create([
                'role_id' => Role::where('name', 'OperationalUser')->value('id'),
                'user_type_id' => UserType::where('name', 'customer')->value('id'),
                'status' => true,
                'is_owner' => false,
            ]);

            $user->userPermission()->create(UserPermission::defaultsFor('customer'));

            return $user;
        });
    }

    private function backToFront(string $path, array $query)
    {
        $base = rtrim((string) config('services.frontend_url'), '/');

        return redirect()->away($base . $path . '?' . http_build_query($query));
    }
}
