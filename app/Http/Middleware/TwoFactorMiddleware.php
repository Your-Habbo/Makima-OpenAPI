<?php
// app/Http/Middleware/TwoFactorMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Check if user has any 2FA methods enabled
        $hasTwoFactor = $user->two_factor_enabled || $user->hasWebAuthnKeys();

        if ($hasTwoFactor && !session('2fa_verified')) {
            // Allow access to 2FA verification endpoints
            $allowedRoutes = [
                'api/auth/2fa/verify',
                'api/auth/webauthn/authenticate',
                'api/auth/webauthn/authentication/options',
                'api/auth/logout',
            ];

            $currentRoute = $request->getPathInfo();

            foreach ($allowedRoutes as $route) {
                if (str_contains($currentRoute, $route)) {
                    return $next($request);
                }
            }

            return response()->json([
                'message' => 'Two-factor authentication required',
                'two_factor_required' => true,
                'available_methods' => $user->getTwoFactorMethods(),
            ], 403);
        }

        return $next($request);
    }
}
