<?php

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request): ?string {
            return match ($request->path()) {
                'admin.html' => '/admin-login.html',
                'teknisi.html' => '/teknisi-login.html',
                'owner.html' => '/owner-login.html',
                'spareparts.html' => '/login.html',
                default => $request->is('api/*') ? null : '/login.html',
            };
        });
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'password.changed' => EnsurePasswordChanged::class,
        ]);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            if ($exception instanceof TokenMismatchException) {
                $status = 419;
            } elseif ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
            } else {
                $status = 500;
            }

            if (! in_array($status, [403, 404, 419, 429, 500], true)) {
                return null;
            }
            if ($status === 500 && config('app.debug')) {
                return null;
            }

            return response()->view('errors.'.$status, [], $status);
        });
    })->create();
