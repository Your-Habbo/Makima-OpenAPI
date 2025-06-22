<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TwoFactorService
{
    /**
     * Generate backup codes for 2FA
     */
    public function generateBackupCodes(): array
    {
        return Collection::times(8, function () {
            return strtoupper(Str::random(8));
        })->toArray();
    }

    /**
     * Verify recovery code
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $recoveryCodes = $user->two_factor_recovery_codes;

        if (!$recoveryCodes || !in_array(strtoupper($code), $recoveryCodes)) {
            return false;
        }

        // Remove used recovery code
        $updatedCodes = array_values(array_diff($recoveryCodes, [strtoupper($code)]));
        
        $user->forceFill([
            'two_factor_recovery_codes' => $updatedCodes,
        ])->save();

        return true;
    }

    /**
     * Send 2FA code via email
     */
    public function sendEmailCode(User $user): string
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store code in cache for 10 minutes
        cache()->put("2fa_email_code_{$user->id}", $code, 600);
        
        $user->notify(new TwoFactorCodeNotification($code));
        
        return $code;
    }

    /**
     * Verify email 2FA code
     */
    public function verifyEmailCode(User $user, string $code): bool
    {
        $storedCode = cache()->get("2fa_email_code_{$user->id}");
        
        if ($storedCode && $storedCode === $code) {
            cache()->forget("2fa_email_code_{$user->id}");
            return true;
        }
        
        return false;
    }
}