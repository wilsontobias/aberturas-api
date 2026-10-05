<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = $request->user()?->userRole;

        if (!$userRole || !$userRole->status) {
            return response()->json([
                'message' => 'No tenés permiso para realizar esta acción.',
            ], 403);
        }

        $roleName = $userRole->role->name;

        if ($roleName === 'SuperAdmin' || in_array($roleName, $roles)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'No tenés permiso para realizar esta acción.',
        ], 403);
    }
}