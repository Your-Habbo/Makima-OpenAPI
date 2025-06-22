<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * @group Authentication
     * 
     * Login user
     * 
     * Authenticate user and return access token
     * 
     * @bodyParam login string required The user's email or username. Example: user@example.com
     * @bodyParam password string required The user's password. Example: password123
     * @bodyParam device_name string required Device name for token. Example: iPhone 12
     * 
     * @response 200 {
     *   "user": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "john@example.com",
     *     "username": "johndoe"
     *   },
     *   "token": "1|xxxxxxxxxxxxxxxxxxxx",
     *   "two_factor_required": false
     * }
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

        // Update last login info
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Check if 2FA is enabled
        if ($user->two_factor_enabled) {
            // Store temp session for 2FA verification
            session(['2fa_user_id' => $user->id]);
            
            return response()->json([
                'two_factor_required' => true,
                'message' => 'Two-factor authentication required',
            ]);
        }

        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'user' => new UserResource($user->load('profile', 'roles')),
            'token' => $token,
            'two_factor_required' => false,
        ]);
    }

    /**
     * @group Authentication
     * 
     * Logout user
     * 
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
     * 
     * Logout from all devices
     * 
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