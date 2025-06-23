<?php
// app/Http/Middleware/WebAuthnMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebAuthnMiddleware
{
    /**
     * Handle an incoming request for WebAuthn-protected routes
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Authentication required',
            ], 401);
        }

        // Check if browser supports WebAuthn
        $userAgent = $request->userAgent();
        if (!$this->isWebAuthnSupported($userAgent)) {
            return response()->json([
                'message' => 'WebAuthn is not supported by your browser',
                'supported_browsers' => [
                    'Chrome 67+',
                    'Firefox 60+',
                    'Safari 14+',
                    'Edge 18+',
                ],
            ], 400);
        }

        // Check if user has WebAuthn keys registered for certain operations
        $protectedRoutes = [
            'api/auth/webauthn/register',
        ];

        $currentRoute = $request->getPathInfo();
        $isProtectedRoute = collect($protectedRoutes)->contains(function ($route) use ($currentRoute) {
            return str_contains($currentRoute, $route);
        });

        if ($isProtectedRoute && !$user->hasWebAuthnKeys() && !$user->two_factor_enabled) {
            return response()->json([
                'message' => 'At least one authentication method must be enabled before adding WebAuthn keys',
                'suggestion' => 'Enable TOTP two-factor authentication first',
            ], 400);
        }

        return $next($request);
    }

    /**
     * Check if the browser supports WebAuthn
     */
    private function isWebAuthnSupported(string $userAgent): bool
    {
        // Chrome 67+
        if (preg_match('/Chrome\/(\d+)/', $userAgent, $matches)) {
            return (int)$matches[1] >= 67;
        }

        // Firefox 60+
        if (preg_match('/Firefox\/(\d+)/', $userAgent, $matches)) {
            return (int)$matches[1] >= 60;
        }

        // Safari 14+
        if (preg_match('/Version\/(\d+).*Safari/', $userAgent, $matches)) {
            return (int)$matches[1] >= 14;
        }

        // Edge 18+
        if (preg_match('/Edg\/(\d+)/', $userAgent, $matches)) {
            return (int)$matches[1] >= 18;
        }

        // Allow unknown browsers (mobile apps, etc.)
        return true;
    }
}
