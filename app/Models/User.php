<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, LogsActivity;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'phone',
        'phone_verified',
        'two_factor_enabled',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'last_login_at' => 'datetime',
        'phone_verified' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'is_active' => 'boolean',
        'two_factor_recovery_codes' => 'encrypted:array',
        'password' => 'hashed',
    ];

    // Relationships
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function webauthnKeys(): HasMany
    {
        return $this->hasMany(WebAuthnKey::class);
    }

    // Enhanced activity log configuration - SINGLE METHOD ONLY
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'username', 'two_factor_enabled'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => match($eventName) {
                'webauthn_key_added' => 'Added WebAuthn security key',
                'webauthn_key_removed' => 'Removed WebAuthn security key',
                'webauthn_login' => 'Signed in with WebAuthn key',
                'two_factor_enabled' => 'Enabled two-factor authentication',
                'two_factor_disabled' => 'Disabled two-factor authentication',
                default => "User {$eventName}",
            });
    }

    // Existing 2FA methods
    public function generateTwoFactorSecret()
    {
        $this->forceFill([
            'two_factor_secret' => encrypt(app('pragmarx.google2fa')->generateSecretKey()),
        ])->save();
    }

    public function confirmTwoFactorAuth()
    {
        $this->forceFill([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    public function hasVerifiedTwoFactor(): bool
    {
        return $this->two_factor_enabled && !is_null($this->two_factor_confirmed_at);
    }

    // WebAuthn methods
    /**
     * Check if user has WebAuthn keys registered
     */
    public function hasWebAuthnKeys(): bool
    {
        return $this->webauthnKeys()->exists();
    }

    /**
     * Get count of registered WebAuthn keys
     */
    public function getWebAuthnKeysCount(): int
    {
        return $this->webauthnKeys()->count();
    }

    /**
     * Check if user has any form of 2FA enabled
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled || $this->hasWebAuthnKeys();
    }

    /**
     * Get user's available 2FA methods
     */
    public function getTwoFactorMethods(): array
    {
        $methods = [];

        if ($this->two_factor_enabled) {
            $methods[] = 'totp';
        }

        if ($this->hasWebAuthnKeys()) {
            $methods[] = 'webauthn';
        }

        return $methods;
    }

    /**
     * Get count of active security methods
     */
    public function getSecurityMethodsCount(): int
    {
        $count = 0;

        if ($this->two_factor_enabled) {
            $count++;
        }

        $count += $this->webauthnKeys()->count();

        return $count;
    }

    /**
     * Check if user can safely remove a security method
     */
    public function canRemoveSecurityMethod(): bool
    {
        return $this->getSecurityMethodsCount() > 1;
    }

    /**
     * Get user's most recently used WebAuthn key
     */
    public function getLastUsedWebAuthnKey(): ?WebAuthnKey
    {
        return $this->webauthnKeys()
            ->whereNotNull('last_used_at')
            ->orderBy('last_used_at', 'desc')
            ->first();
    }

    /**
     * Get user's WebAuthn keys by transport type
     */
    public function getWebAuthnKeysByTransport(string $transport): \Illuminate\Database\Eloquent\Collection
    {
        return $this->webauthnKeys()
            ->whereJsonContains('transports', $transport)
            ->get();
    }

    /**
     * Check if user has platform authenticators (Touch ID, Face ID, Windows Hello)
     */
    public function hasPlatformAuthenticators(): bool
    {
        return $this->webauthnKeys()
            ->whereJsonContains('transports', 'internal')
            ->exists();
    }

    /**
     * Check if user has external security keys (USB, NFC, BLE)
     */
    public function hasExternalSecurityKeys(): bool
    {
        return $this->webauthnKeys()
            ->where(function ($query) {
                $query->whereJsonContains('transports', 'usb')
                      ->orWhereJsonContains('transports', 'nfc')
                      ->orWhereJsonContains('transports', 'ble');
            })
            ->exists();
    }

    /**
     * Get user's security overview
     */
    public function getSecurityOverview(): array
    {
        $webauthnKeys = $this->webauthnKeys;

        return [
            'two_factor_enabled' => $this->two_factor_enabled,
            'webauthn_enabled' => $this->hasWebAuthnKeys(),
            'total_security_methods' => $this->getSecurityMethodsCount(),
            'webauthn_keys_count' => $webauthnKeys->count(),
            'platform_authenticators' => $webauthnKeys->filter(function ($key) {
                return in_array('internal', $key->transports ?? []);
            })->count(),
            'external_keys' => $webauthnKeys->filter(function ($key) {
                $transports = $key->transports ?? [];
                return array_intersect(['usb', 'nfc', 'ble'], $transports);
            })->count(),
            'backup_eligible_keys' => $webauthnKeys->where('backup_eligible', true)->count(),
            'last_webauthn_used' => $this->getLastUsedWebAuthnKey()?->last_used_at,
            'can_remove_method' => $this->canRemoveSecurityMethod(),
        ];
    }

    /**
     * Disable all 2FA methods (use with caution)
     */
    public function disableAllTwoFactor(): bool
    {
        // Disable TOTP 2FA
        $this->forceFill([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        // Remove all WebAuthn keys
        $this->webauthnKeys()->delete();

        return true;
    }

    /**
     * Check if user needs to set up any 2FA method
     */
    public function needsTwoFactorSetup(): bool
    {
        return !$this->hasTwoFactorEnabled();
    }

    /**
     * Get recommended next security step
     */
    public function getRecommendedSecurityStep(): ?string
    {
        if (!$this->hasTwoFactorEnabled()) {
            return 'setup_first_2fa';
        }

        if ($this->two_factor_enabled && !$this->hasWebAuthnKeys()) {
            return 'add_webauthn_key';
        }

        if ($this->hasWebAuthnKeys() && !$this->two_factor_enabled) {
            return 'add_totp_backup';
        }

        if ($this->getSecurityMethodsCount() === 1) {
            return 'add_backup_method';
        }

        return null;
    }
}
