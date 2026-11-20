<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $allowedRoutes = ['api/auth/me', 'api/auth/logout', 'api/auth/password', 'password/change'];

        if ($user?->must_change_password && ! in_array($request->path(), $allowedRoutes, true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda harus mengganti kata sandi sebelum melanjutkan.',
                    'password_change_url' => url('/password/change'),
                ], 403);
            }

            return redirect('/password/change');
        }

        return $next($request);
    }
}
