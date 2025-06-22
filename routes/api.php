<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\TwoFactorController;
use App\Http\Controllers\Api\User\ProfileController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('login', [LoginController::class, 'login']);
    Route::post('register', [RegisterController::class, 'register']);
});

// Two Factor Authentication verification (no auth required)
Route::prefix('2fa')->group(function () {
    Route::post('verify', [TwoFactorController::class, 'verify']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication
    Route::prefix('auth')->group(function () {
        Route::post('logout', [LoginController::class, 'logout']);
        Route::post('logout-all', [LoginController::class, 'logoutFromAllDevices']);
        Route::get('me', [ProfileController::class, 'me']);
    });

    // Two Factor Authentication
    Route::prefix('2fa')->group(function () {
        Route::post('enable', [TwoFactorController::class, 'enable']);
        Route::post('confirm', [TwoFactorController::class, 'confirm']);
        Route::post('disable', [TwoFactorController::class, 'disable']);
        Route::get('recovery-codes', [TwoFactorController::class, 'recoveryCodes']);
        Route::post('recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes']);
        Route::post('email/send', [TwoFactorController::class, 'sendEmailCode']);
        Route::post('email/verify', [TwoFactorController::class, 'verifyEmailCode']);
    });

    // User Profile
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::post('avatar', [ProfileController::class, 'uploadAvatar']);
    });

    // Admin routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('permissions', PermissionController::class);
        Route::apiResource('users', UserManagementController::class);
    });
});