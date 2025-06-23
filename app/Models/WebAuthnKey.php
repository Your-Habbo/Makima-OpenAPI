<?php
// app/Models/WebAuthnKey.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebAuthnKey extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'webauthn_keys';

    protected $fillable = [
        'user_id',
        'name',
        'credential_id',
        'public_key',
        'counter',
        'aaguid',
        'transports',
        'backup_eligible',
        'backup_state',
        'last_used_at',
    ];

    protected $casts = [
        'transports' => 'array',
        'backup_eligible' => 'boolean',
        'backup_state' => 'boolean',
        'last_used_at' => 'datetime',
        'counter' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the credential ID as binary
     */
    public function getCredentialIdBinary(): string
    {
        return base64_decode($this->credential_id);
    }

    /**
     * Set the credential ID from binary
     */
    public function setCredentialIdFromBinary(string $binaryId): void
    {
        $this->credential_id = base64_encode($binaryId);
    }

    /**
     * Get the public key as binary
     */
    public function getPublicKeyBinary(): string
    {
        return base64_decode($this->public_key);
    }

    /**
     * Set the public key from binary
     */
    public function setPublicKeyFromBinary(string $binaryKey): void
    {
        $this->public_key = base64_encode($binaryKey);
    }

    /**
     * Update the signature counter
     */
    public function updateCounter(int $newCounter): bool
    {
        if ($newCounter <= $this->counter) {
            return false; // Counter should always increase
        }

        $this->counter = $newCounter;
        $this->last_used_at = now();
        return $this->save();
    }

    /**
     * Check if this key supports a specific transport
     */
    public function supportsTransport(string $transport): bool
    {
        return in_array($transport, $this->transports ?? []);
    }

    /**
     * Get a user-friendly description of the authenticator
     */
    public function getAuthenticatorDescription(): string
    {
        $transports = $this->transports ?? [];

        if (in_array('internal', $transports)) {
            return 'Platform Authenticator (Face ID, Touch ID, Windows Hello)';
        } elseif (in_array('usb', $transports)) {
            return 'USB Security Key';
        } elseif (in_array('nfc', $transports)) {
            return 'NFC Security Key';
        } elseif (in_array('ble', $transports)) {
            return 'Bluetooth Security Key';
        }

        return 'Security Key';
    }
}
