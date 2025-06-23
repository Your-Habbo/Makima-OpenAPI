<?php
// app/Http/Controllers/Api/Auth/LoginController.php - FIXED with Type Hints

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * @group Authentication
     * Login user
     * Authenticate user and return access token
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $key = 'login.' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'login' => ['Too many login attempts. Please try again later.'],
            ]);
        }

        $credentials = $request->getCredentials();

        // Record login attempt
        LoginAttempt::recordAttempt(
            $credentials['email'] ?? $credentials['username'] ?? '',
            $request->ip(),
            false,
            $request->userAgent()
        );

        if (!Auth::attempt($credentials)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['Your account has been deactivated.'],
            ]);
        }

        // Clear rate limiter on successful login
        RateLimiter::clear($key);

        // Record successful login
        LoginAttempt::recordAttempt(
            $user->email,
            $request->ip(),
            true,
            $request->userAgent()
        );

        // FIXED: Check for ANY 2FA method (TOTP OR WebAuthn) with proper type casting
        $hasTotpEnabled = (bool) $user->two_factor_enabled;
        $hasWebAuthnKeys = $user->webauthnKeys()->exists();
        $requiresSecondFactor = $hasTotpEnabled || $hasWebAuthnKeys;

        if ($requiresSecondFactor) {
            // Start session explicitly to ensure it's available
            if (!session()->isStarted()) {
                session()->start();
            }

            // Store temp session for 2FA/WebAuthn verification with multiple fallbacks
            session(['2fa_user_id' => $user->id]);
            session()->save(); // Force session save

            // Additional fallback storage using cache
            $sessionId = session()->getId();
            Cache::put("2fa_user_id_{$sessionId}", $user->id, now()->addMinutes(10));

            // Another fallback using IP + User-Agent
            $cacheKey = '2fa_user_id_' . md5($request->ip() . $request->userAgent());
            Cache::put($cacheKey, $user->id, now()->addMinutes(10));

            // Determine available authentication methods
            $availableMethods = [];
            if ($hasTotpEnabled) {
                $availableMethods[] = 'totp';
            }
            if ($hasWebAuthnKeys) {
                $availableMethods[] = 'webauthn';
            }

            // DEBUGGING
            Log::debug('Multi-factor authentication required', [
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'has_totp' => $hasTotpEnabled,
                'has_webauthn' => $hasWebAuthnKeys,
                'webauthn_keys_count' => $user->webauthnKeys()->count(),
                'available_methods' => $availableMethods,
            ]);

            return response()->json([
                'two_factor_required' => true,
                'webauthn_required' => $hasWebAuthnKeys, // NEW: Indicate WebAuthn is available
                'message' => 'Multi-factor authentication required',
                'available_methods' => $availableMethods, // NEW: Show all available methods
                'session_id' => $sessionId, // For debugging purposes
            ])->withCookie(
                cookie()->make(
                    config('session.cookie'),
                    $sessionId,
                    config('session.lifetime'),
                    config('session.path'),
                    config('session.domain'),
                    config('session.secure'),
                    config('session.http_only'),
                    false,
                    config('session.same_site')
                )
            );
        }

        // No 2FA required - immediate login
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $token = $user->createToken($request->device_name)->plainTextToken;

        Log::info('User logged in without 2FA', [
            'user_id' => $user->id,
            'has_totp' => $hasTotpEnabled,
            'has_webauthn' => $hasWebAuthnKeys,
        ]);

        return response()->json([
            'user' => new UserResource($user->load('profile', 'roles')),
            'token' => $token,
            'two_factor_required' => false,
            'webauthn_required' => false,
        ]);
    }

    /**
     * @group Authentication
     * Logout user
     * Revoke current access token
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    /**
     * @group Authentication
     * Logout from all devices
     * Revoke all user's access tokens
     */
    public function logoutFromAllDevices(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Successfully logged out from all devices',
        ]);
    }
}
