<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\TwoFactorRequest;
use App\Http\Resources\UserResource;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TwoFactorController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService,
        private Google2FA $google2fa
    ) {}

    /**
     * @group Two-Factor Authentication
     * 
     * Enable 2FA
     * 
     * Generate QR code and secret for 2FA setup
     * 
     * @response 200 {
     *   "qr_code": "data:image/svg+xml;base64,PHN2Zy4uLg==",
     *   "secret": "JBSWY3DPEHPK3PXP",
     *   "backup_codes": ["12345678", "87654321"]
     * }
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

        return response()->json([
            'qr_code' => QrCode::format('svg')->size(200)->generate($qrCodeUrl),
            'secret' => $secret,
            'backup_codes' => $backupCodes,
        ]);
    }

    /**
     * @group Two-Factor Authentication
     * 
     * Confirm 2FA
     * 
     * Verify OTP code and enable 2FA
     * 
     * @bodyParam code string required The OTP code from authenticator app. Example: 123456
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
     * 
     * Complete login with 2FA code
     * 
     * @bodyParam code string required The OTP code. Example: 123456
     * @bodyParam recovery_code string optional Recovery code if OTP not available. Example: 12345678
     * @bodyParam device_name string required Device name for token. Example: iPhone 12
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required_without:recovery_code|string|size:6',
            'recovery_code' => 'required_without:code|string',
            'device_name' => 'required|string',
        ]);

        $userId = session('2fa_user_id');
        if (!$userId) {
            throw ValidationException::withMessages([
                'code' => ['Two-factor authentication session expired.'],
            ]);
        }

        $user = User::find($userId);
        if (!$user || !$user->two_factor_enabled) {
            throw ValidationException::withMessages([
                'code' => ['Two-factor authentication is not enabled.'],
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

        // Clear 2FA session
        session()->forget('2fa_user_id');

        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'user' => new UserResource($user->load('profile', 'roles')),
            'token' => $token,
        ]);
    }

    /**
     * @group Two-Factor Authentication
     * 
     * Disable 2FA
     * 
     * @bodyParam password string required Current password for verification. Example: password123
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

        $code = $this->twoFactorService->sendEmailCode($user);

        return response()->json([
            'message' => 'Two-factor authentication code sent to your email.',
        ]);
    }

    /**
     * @group Two-Factor Authentication
     * 
     * Verify Email 2FA Code
     * 
     * @bodyParam code string required The email OTP code. Example: 123456
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