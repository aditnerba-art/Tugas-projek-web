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
            return ($request->expectsJson() || $request->is('api/*'))
                ? response()->json(['status' => 'error', 'message' => 'Silakan login terlebih dahulu'], 401)
                : redirect('/login.html');
        }

        if (! in_array($user->role, $roles, true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['status' => 'error', 'message' => 'Akses tidak diizinkan untuk peran ini'], 403);
            }

            return redirect('/'.$user->role.'.html');
        }

        return $next($request);
    }
}
