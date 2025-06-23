<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Add all necessary middleware for stateful API routes
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class, // For cross-origin requests
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class, // Share validation errors
        ]);

        // Add middleware for web routes to ensure proper session handling
        $middleware->web(append: [
            \Illuminate\Session\Middleware\AuthenticateSession::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            '2fa' => \App\Http\Middleware\TwoFactorMiddleware::class,
            'webauthn' => \App\Http\Middleware\WebAuthnMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'stripe/*',
            'api/*', // Exclude all API routes from CSRF protection
            'auth/webauthn/*', // Specifically exclude WebAuthn routes
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle WebAuthn specific exceptions
        $exceptions->render(function (\App\Exceptions\WebAuthnException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'webauthn_error',
            ], $e->getStatusCode());
        });

        // Handle 2FA exceptions
        $exceptions->render(function (\App\Exceptions\TwoFactorException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'two_factor_error',
                'two_factor_required' => true,
            ], $e->getStatusCode());
        });
    })->create();
