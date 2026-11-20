<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['status' => 'error', 'message' => 'Silakan login terlebih dahulu'], 401);
            }

            return count($roles) === 1
                ? redirect('/'.$roles[0].'-login.html')
                : redirect('/login.html');
        }

        if (! in_array($user->role, $roles, true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['status' => 'error', 'message' => 'Akses tidak diizinkan untuk peran ini'], 403);
            }

            abort(403);
        }

        return $next($request);
    }
}
