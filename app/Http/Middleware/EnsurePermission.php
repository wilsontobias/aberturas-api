<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        $userRole = $user?->userRole;

        if (!$userRole || !$userRole->status) {
            return response()->json([
                'message' => 'No tenés permiso para realizar esta acción.',
            ], 403);
        }

        $roleName = $userRole->role->name;

        if ($roleName === 'SuperAdmin' || $roleName === 'Administrator') {
            return $next($request);
        }

        $permissions = $user->userPermission;

        if ($permissions && $permissions->getAttribute($permission)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'No tenés permiso para realizar esta acción.',
        ], 403);
    }
}