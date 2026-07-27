<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'ADMIN') {
            return response()->json([
                'message' => 'No tienes permisos para acceder al panel administrativo.',
            ], 403);
        }

        return $next($request);
    }
}
