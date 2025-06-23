<?php
// app/Http/Controllers/Api/Auth/TwoFactorController.php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\TwoFactorRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService,
        private Google2FA $google2fa
    ) {
    }

    /**
     * @group Two-Factor Authentication
     *
     * Enable 2FA
     *
     * Generate QR code and secret for 2FA setup
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_enabled) {
            return response()->json([
                'message' => 'Two-factor authentication is already enabled.',
            ], 400);
        }

        // Generate secret
        $user->generateTwoFactorSecret();

        // Generate backup codes
        $backupCodes = $this->twoFactorService->generateBackupCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => $backupCodes,
        ])->save();

        $secret = decrypt($user->two_factor_secret);
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $qrCodeImage = 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . urlencode($qrCodeUrl);

        return response()->json([
            'qr_code' => $qrCodeImage,
            'secret' => $secret,
            'backup_codes' => $backupCodes,
        ]);
    }

    /**
     * @group Two-Factor Authentication
     *
     * Confirm 2FA
     */
    public function confirm(TwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();
        $code = $request->input('code');

        if ($user->two_factor_enabled) {
            return response()->json([
                'message' => 'Two-factor authentication is already confirmed.',
            ], 400);
        }

        $valid = $this->google2fa->verifyKey(
            decrypt($user->two_factor_secret),
            $code
        );

        if (!$valid) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is invalid.'],
            ]);
        }

        $user->confirmTwoFactorAuth();

        return response()->json([
            'message' => 'Two-factor authentication confirmed successfully.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * @group Two-Factor Authentication
     *
     * Verify 2FA Login
     */
    public function verify(TwoFactorRequest $request): JsonResponse
    {
        // Start the session explicitly to ensure it's available
        if (!session()->isStarted()) {
            session()->start();
        }

        // --- DEBUGGING ---
        Log::debug('2FA Verify: Request received. Session ID: ' . session()->getId());
        Log::debug('2FA Verify: Session data:', session()->all());
        Log::debug('2FA Verify: Request headers:', $request->headers->all());
        Log::debug('2FA Verify: Request cookies:', $request->cookies->all());
        // --- END DEBUGGING ---

        // Try multiple methods to get the user ID
        $userId = session('2fa_user_id');

        // Fallback: try to get from cache using session ID as key
        if (!$userId) {
            $sessionId = session()->getId();
            $userId = Cache::get("2fa_user_id_{$sessionId}");
            Log::debug('2FA Verify: Fallback cache lookup for session ' . $sessionId . ', found user ID: ' . ($userId ?: 'none'));
        }

        // Fallback: try to get from cache using IP + User-Agent as key
        if (!$userId) {
            $cacheKey = '2fa_user_id_' . md5($request->ip() . $request->userAgent());
            $userId = Cache::get($cacheKey);
            Log::debug('2FA Verify: Fallback IP+UA cache lookup, found user ID: ' . ($userId ?: 'none'));
        }

        if (!$userId) {
            // --- DEBUGGING ---
            Log::error('2FA Verify: No user ID found in session or cache. Session expired or invalid.');
            Log::error('2FA Verify: Session ID: ' . session()->getId());
            Log::error('2FA Verify: All session data: ', session()->all());
            // --- END DEBUGGING ---

            throw ValidationException::withMessages([
                'code' => ['Two-factor authentication session expired. Please login again.'],
            ]);
        }

        // --- DEBUGGING ---
        Log::info('2FA Verify: Found user ID: ' . $userId);
        // --- END DEBUGGING ---

        $user = User::find($userId);
        if (!$user || !$user->two_factor_enabled) {
            throw ValidationException::withMessages([
                'code' => ['Two-factor authentication is not enabled for this user.'],
            ]);
        }

        $valid = false;

        if ($request->filled('code')) {
            $valid = $this->google2fa->verifyKey(
                decrypt($user->two_factor_secret),
                $request->input('code')
            );
        } elseif ($request->filled('recovery_code')) {
            $valid = $this->twoFactorService->verifyRecoveryCode(
                $user,
                $request->input('recovery_code')
            );
        }

        if (!$valid) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is invalid.'],
            ]);
        }

        // Clean up all stored references
        session()->forget('2fa_user_id');
        $sessionId = session()->getId();
        Cache::forget("2fa_user_id_{$sessionId}");
        $cacheKey = '2fa_user_id_' . md5($request->ip() . $request->userAgent());
        Cache::forget($cacheKey);

        // Update last login info
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $token = $user->createToken($request->input('device_name', 'Browser'))->plainTextToken;

        Log::info('2FA Verify: Successfully verified 2FA for user ID: ' . $user->id);

        return response()->json([
            'user' => new UserResource($user->load('profile', 'roles')),
            'token' => $token,
        ]);
    }

    /**
     * @group Two-Factor Authentication
     *
     * Disable 2FA
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|current_password',
        ]);

        $user = $request->user();

        $user->forceFill([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json([
            'message' => 'Two-factor authentication disabled successfully.',
        ]);
    }

    /**
     * @group Two-Factor Authentication
     *
     * Get Recovery Codes
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->two_factor_enabled) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled.',
            ], 400);
        }

        return response()->json([
            'recovery_codes' => $user->two_factor_recovery_codes,
        ]);
    }

    /**
     * @group Two-Factor Authentication
     *
     * Regenerate Recovery Codes
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->two_factor_enabled) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled.',
            ], 400);
        }

        $backupCodes = $this->twoFactorService->generateBackupCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => $backupCodes,
        ])->save();

        return response()->json([
            'recovery_codes' => $backupCodes,
        ]);
    }

    /**
     * @group Two-Factor Authentication
     *
     * Send Email 2FA Code
     */
    public function sendEmailCode(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->twoFactorService->sendEmailCode($user);

        return response()->json([
            'message' => 'Two-factor authentication code sent to your email.',
        ]);
    }

    /**
     * @group Two-Factor Authentication
     *
     * Verify Email 2FA Code
     */
    public function verifyEmailCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        $code = $request->input('code');

        $valid = $this->twoFactorService->verifyEmailCode($user, $code);

        if (!$valid) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is invalid or expired.'],
            ]);
        }

        return response()->json([
            'message' => 'Email two-factor authentication verified successfully.',
        ]);
    }
}
