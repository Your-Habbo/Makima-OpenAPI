<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\TwoFactorController;
use App\Http\Controllers\Api\Auth\WebAuthnController;
use App\Http\Controllers\Api\User\ProfileController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use Illuminate\Support\Facades\Route;

// Public authentication routes with strict rate limiting
Route::prefix('auth')->group(function () {
    // Login and registration with strict rate limiting (5 attempts per minute)
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('login', [LoginController::class, 'login']);
        Route::post('register', [RegisterController::class, 'register']);
    });
});

// Two Factor Authentication verification (no auth required) - strict rate limiting
Route::prefix('2fa')->middleware('throttle:5,1')->group(function () {
    Route::post('verify', [TwoFactorController::class, 'verify']);
});

// WebAuthn public routes with moderate rate limiting
Route::prefix('auth/webauthn')->group(function () {
    // Authentication options and verification (10 attempts per minute)
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('authentication/options', [WebAuthnController::class, 'authenticationOptions']);
        Route::post('authenticate', [WebAuthnController::class, 'authenticate']);
    });
});

// Protected routes requiring authentication
Route::middleware('auth:sanctum')->group(function () {
    // Authentication management
    Route::prefix('auth')->group(function () {
        // Logout routes with moderate rate limiting (10 attempts per minute)
        Route::middleware('throttle:10,1')->group(function () {
            Route::post('logout', [LoginController::class, 'logout']);
            Route::post('logout-all', [LoginController::class, 'logoutFromAllDevices']);
        });

        // User info with standard rate limiting (20 attempts per minute)
        Route::middleware('throttle:20,1')->group(function () {
            Route::get('me', [ProfileController::class, 'me']);
        });
    });

    // Two Factor Authentication management
    Route::prefix('2fa')->middleware('throttle:15,1')->group(function () {
        Route::post('enable', [TwoFactorController::class, 'enable']);
        Route::post('confirm', [TwoFactorController::class, 'confirm']);
        Route::post('disable', [TwoFactorController::class, 'disable']);
        Route::get('recovery-codes', [TwoFactorController::class, 'recoveryCodes']);
        Route::post('recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes']);
        Route::post('email/send', [TwoFactorController::class, 'sendEmailCode']);
        Route::post('email/verify', [TwoFactorController::class, 'verifyEmailCode']);
    });

    // WebAuthn management routes (protected)
    Route::prefix('auth/webauthn')->middleware('throttle:15,1')->group(function () {
        Route::get('registration/options', [WebAuthnController::class, 'registrationOptions']);
        Route::post('register', [WebAuthnController::class, 'register']);
        Route::get('keys', [WebAuthnController::class, 'keys']);
        Route::delete('keys/{keyId}', [WebAuthnController::class, 'deleteKey'])->where('keyId', '[0-9]+');
        Route::put('keys/{keyId}/name', [WebAuthnController::class, 'updateKeyName'])->where('keyId', '[0-9]+');
    });

    // User Profile management
    Route::prefix('profile')->middleware('throttle:20,1')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::post('avatar', [ProfileController::class, 'uploadAvatar']);
    });

    // Security overview and management
    Route::prefix('security')->middleware('throttle:20,1')->group(function () {
        Route::get('overview', function () {
            return response()->json([
                'security' => auth()->user()->getSecurityOverview(),
                'recommendations' => [
                    'next_step' => auth()->user()->getRecommendedSecurityStep(),
                    'methods_available' => auth()->user()->getTwoFactorMethods(),
                ],
            ]);
        });

        Route::get('methods', function () {
            return response()->json([
                'available_methods' => auth()->user()->getTwoFactorMethods(),
                'webauthn_keys' => auth()->user()->webauthnKeys->map(function ($key) {
                    return [
                        'id' => $key->id,
                        'name' => $key->name,
                        'description' => $key->getAuthenticatorDescription(),
                        'last_used_at' => $key->last_used_at,
                        'created_at' => $key->created_at,
                    ];
                }),
                'totp_enabled' => auth()->user()->two_factor_enabled,
            ]);
        });
    });

    // Admin routes with standard rate limiting
    Route::middleware(['role:admin', 'throttle:30,1'])->prefix('admin')->group(function () {
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('permissions', PermissionController::class);
        Route::apiResource('users', UserManagementController::class);

        // Admin security management
        Route::prefix('security')->group(function () {
            Route::get('stats', function () {
                return response()->json([
                    'total_users' => \App\Models\User::count(),
                    'users_with_2fa' => \App\Models\User::where('two_factor_enabled', true)->count(),
                    'users_with_webauthn' => \App\Models\User::has('webauthnKeys')->count(),
                    'total_webauthn_keys' => \App\Models\WebAuthnKey::count(),
                    'platform_authenticators' => \App\Models\WebAuthnKey::whereJsonContains('transports', 'internal')->count(),
                    'external_keys' => \App\Models\WebAuthnKey::where(function ($query) {
                        $query->whereJsonContains('transports', 'usb')
                              ->orWhereJsonContains('transports', 'nfc')
                              ->orWhereJsonContains('transports', 'ble');
                    })->count(),
                ]);
            });

            Route::get('users/{userId}/security', function ($userId) {
                $user = \App\Models\User::findOrFail($userId);
                return response()->json([
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                    'security' => $user->getSecurityOverview(),
                ]);
            })->where('userId', '[0-9]+');
        });
    });
});

// Health check and rate limit status (public, light rate limiting)
Route::middleware('throttle:60,1')->group(function () {
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now(),
            'version' => '1.0.0',
        ]);
    });

    Route::get('auth/rate-limit-status', function () {
        return response()->json([
            'message' => 'Rate limit check successful',
            'timestamp' => now(),
            'ip' => request()->ip(),
        ]);
    });
});

// WebAuthn capability check (public, no rate limiting needed)
Route::get('auth/webauthn/supported', function () {
    $userAgent = request()->userAgent();
    $supported = false;

    // Basic browser support detection
    if (preg_match('/Chrome\/(\d+)/', $userAgent, $matches) && (int)$matches[1] >= 67) {
        $supported = true;
    } elseif (preg_match('/Firefox\/(\d+)/', $userAgent, $matches) && (int)$matches[1] >= 60) {
        $supported = true;
    } elseif (preg_match('/Version\/(\d+).*Safari/', $userAgent, $matches) && (int)$matches[1] >= 14) {
        $supported = true;
    } elseif (preg_match('/Edg\/(\d+)/', $userAgent, $matches) && (int)$matches[1] >= 18) {
        $supported = true;
    }

    return response()->json([
        'supported' => $supported,
        'user_agent' => $userAgent,
        'recommendation' => $supported ?
            'WebAuthn is supported. You can use security keys or biometric authentication.' :
            'WebAuthn is not supported. Please use a modern browser like Chrome 67+, Firefox 60+, Safari 14+, or Edge 18+.',
    ]);
});
