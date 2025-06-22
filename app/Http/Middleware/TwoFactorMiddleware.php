<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->two_factor_enabled && !session('2fa_verified')) {
            return response()->json([
                'message' => 'Two-factor authentication required',
                'two_factor_required' => true,
            ], 403);
        }

        return $next($request);
    }
}