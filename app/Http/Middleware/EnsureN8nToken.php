<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Deja pasar solo a n8n: pide el token compartido en el encabezado X-N8N-Token. */
class EnsureN8nToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.n8n.token');
        $received = (string) $request->header('X-N8N-Token', '');

        if ($expected === '' || !hash_equals($expected, $received)) {
            return response()->json([
                'message' => 'Token de n8n inválido o ausente.',
            ], 401);
        }

        return $next($request);
    }
}
