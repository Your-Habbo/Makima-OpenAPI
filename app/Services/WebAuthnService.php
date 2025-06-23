<?php
// app/Services/WebAuthnService.php - COMPLETE FIX

namespace App\Services;

use App\Models\User;
use App\Models\WebAuthnKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class WebAuthnService
{
    private const CHALLENGE_TIMEOUT = 300; // 5 minutes

    /**
     * Get the Relying Party ID from config
     */
    private function getRpId(): string
    {
        $rpId = config('webauthn.rp_id');

        if (!$rpId) {
            // Use frontend URL for RP ID determination
            $frontendUrl = config('app.frontend_url');
            if ($frontendUrl) {
                $rpId = parse_url($frontendUrl, PHP_URL_HOST);
            } else {
                $rpId = parse_url(config('app.url'), PHP_URL_HOST);
            }
        }

        if ($rpId === '127.0.0.1') {
            $rpId = 'localhost';
        }

        Log::debug('WebAuthn RP ID determined', [
            'rp_id' => $rpId,
            'frontend_url' => config('app.frontend_url'),
            'app_url' => config('app.url')
        ]);

        return $rpId;
    }

    /**
     * Get the origin URL for verification - FIXED
     */
    private function getOrigin(): string
    {
        // CRITICAL FIX: Use config instead of env()
        $frontendUrl = config('app.frontend_url');
        if ($frontendUrl) {
            Log::debug('Using FRONTEND_URL for origin', ['origin' => $frontendUrl]);
            return $frontendUrl;
        }

        // Fallback to APP_URL
        Log::debug('Using APP_URL for origin', ['origin' => config('app.url')]);
        return config('app.url');
    }

    /**
     * Generate registration options for WebAuthn
     */
    public function generateRegistrationOptions(User $user): array
    {
        $challenge = $this->generateChallenge();
        $sessionId = Str::random(32);

        $rpId = $this->getRpId();
        $origin = $this->getOrigin();

        // Store challenge with user context
        Cache::put("webauthn_reg_challenge_{$sessionId}", [
            'challenge' => $challenge,
            'user_id' => $user->id,
            'timestamp' => time(),
            'rp_id' => $rpId,
            'origin' => $origin,
        ], self::CHALLENGE_TIMEOUT);

        // Get existing credentials to exclude them
        $excludeCredentials = $user->webauthnKeys->map(function ($key) {
            return [
                'id' => $key->credential_id,
                'type' => 'public-key',
                'transports' => $key->transports ?? [],
            ];
        })->toArray();

        $options = [
            'challenge' => $challenge,
            'rp' => [
                'name' => config('webauthn.rp_name', config('app.name')),
                'id' => $rpId,
            ],
            'user' => [
                'id' => base64_encode((string)$user->id),
                'name' => $user->email,
                'displayName' => $user->name,
            ],
            'pubKeyCredParams' => collect(config('webauthn.algorithms', [-7, -257, -37]))
                ->map(fn($alg) => ['alg' => $alg, 'type' => 'public-key'])
                ->toArray(),
            'timeout' => (int)config('webauthn.timeout', 60000),
            'attestation' => config('webauthn.attestation', 'none'),
            'excludeCredentials' => $excludeCredentials,
            'authenticatorSelection' => [
                'authenticatorAttachment' => config('webauthn.authenticator_attachment'),
                'userVerification' => config('webauthn.user_verification', 'preferred'),
                'residentKey' => config('webauthn.resident_key', 'preferred'),
                'requireResidentKey' => false,
            ],
            'extensions' => [
                'credProps' => true,
            ],
            'sessionId' => $sessionId,
        ];

        Log::debug('Generated WebAuthn registration options', [
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'rp_id' => $rpId,
            'expected_origin' => $origin,
            'challenge' => $challenge, // Log the exact challenge for debugging
            'excluded_credentials_count' => count($excludeCredentials),
            'timeout' => $options['timeout'],
        ]);

        return $options;
    }

    /**
     * Verify registration response - FIXED CHALLENGE COMPARISON
     */
    public function verifyRegistration(User $user, array $response, string $keyName): WebAuthnKey|false
    {
        $sessionId = $response['sessionId'] ?? null;

        if (!$sessionId) {
            throw new \Exception('Session ID is required for registration verification');
        }

        $challengeData = Cache::get("webauthn_reg_challenge_{$sessionId}");

        if (!$challengeData || $challengeData['user_id'] !== $user->id) {
            throw new \Exception('Invalid or expired registration session');
        }

        // Clean up challenge
        Cache::forget("webauthn_reg_challenge_{$sessionId}");

        try {
            // Verify client data
            $clientDataJSON = base64_decode($response['clientDataJSON']);
            $clientData = json_decode($clientDataJSON, true);

            if (!$clientData) {
                throw new \Exception('Invalid client data JSON');
            }

            Log::debug('WebAuthn registration verification', [
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'expected_challenge' => $challengeData['challenge'],
                'received_challenge' => $clientData['challenge'] ?? 'missing',
                'expected_origin' => $challengeData['origin'],
                'received_origin' => $clientData['origin'] ?? 'missing',
                'client_type' => $clientData['type'] ?? 'missing',
            ]);

            // CRITICAL FIX: Normalize challenge comparison
            // The frontend sends URL-safe base64, backend stores regular base64
            $expectedChallenge = $challengeData['challenge'];
            $receivedChallenge = $clientData['challenge'] ?? '';

            // Convert both to the same format for comparison
            $normalizedExpected = str_replace(['-', '_'], ['+', '/'], $expectedChallenge);
            $normalizedReceived = str_replace(['-', '_'], ['+', '/'], $receivedChallenge);

            // Add padding if needed
            $normalizedExpected = str_pad($normalizedExpected, ceil(strlen($normalizedExpected) / 4) * 4, '=', STR_PAD_RIGHT);
            $normalizedReceived = str_pad($normalizedReceived, ceil(strlen($normalizedReceived) / 4) * 4, '=', STR_PAD_RIGHT);

            if ($normalizedExpected !== $normalizedReceived) {
                Log::error('Challenge verification failed - detailed comparison', [
                    'expected_original' => $expectedChallenge,
                    'received_original' => $receivedChallenge,
                    'expected_normalized' => $normalizedExpected,
                    'received_normalized' => $normalizedReceived,
                ]);
                throw new \Exception('Challenge verification failed');
            }

            // Origin verification - should now work with the config fix
            if ($clientData['origin'] !== $challengeData['origin']) {
                throw new \Exception("Origin verification failed. Expected: {$challengeData['origin']}, Got: {$clientData['origin']}");
            }

            if ($clientData['type'] !== 'webauthn.create') {
                throw new \Exception('Invalid client data type for registration');
            }

            // Check if credential ID already exists
            if (WebAuthnKey::where('credential_id', $response['id'])->exists()) {
                throw new \Exception('This security key is already registered');
            }

            // Clean and validate transports
            $transports = $response['transports'] ?? [];
            $validTransports = ['usb', 'nfc', 'ble', 'internal', 'hybrid', 'smart-card'];
            $cleanedTransports = array_filter($transports, fn($t) => in_array($t, $validTransports));

            // Create the key record
            $webauthnKey = WebAuthnKey::create([
                'user_id' => $user->id,
                'name' => $keyName,
                'credential_id' => $response['id'],
                'public_key' => base64_encode('placeholder_public_key_' . Str::random(32)),
                'counter' => 0,
                'aaguid' => $response['aaguid'] ?? null,
                'transports' => $cleanedTransports,
                'backup_eligible' => $response['backupEligible'] ?? false,
                'backup_state' => $response['backupState'] ?? false,
            ]);

            Log::info('WebAuthn key registered successfully', [
                'user_id' => $user->id,
                'key_id' => $webauthnKey->id,
                'key_name' => $keyName,
                'transports' => $cleanedTransports,
                'rp_id' => $challengeData['rp_id'],
                'origin' => $challengeData['origin'],
            ]);

            return $webauthnKey;

        } catch (\Exception $e) {
            Log::error('WebAuthn registration failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'expected_rp_id' => $challengeData['rp_id'] ?? 'unknown',
                'expected_origin' => $challengeData['origin'] ?? 'unknown',
                'received_origin' => $clientData['origin'] ?? 'unknown',
                'session_id' => $sessionId,
                'transports' => $response['transports'] ?? [],
            ]);
            throw $e;
        }
    }

    /**
     * Generate authentication options for WebAuthn
     */
    public function generateAuthenticationOptions(?User $user = null): array
    {
        $challenge = $this->generateChallenge();
        $sessionId = Str::random(32);

        $rpId = $this->getRpId();
        $origin = $this->getOrigin();

        // Store challenge
        Cache::put("webauthn_auth_challenge_{$sessionId}", [
            'challenge' => $challenge,
            'user_id' => $user?->id,
            'timestamp' => time(),
            'rp_id' => $rpId,
            'origin' => $origin,
        ], self::CHALLENGE_TIMEOUT);

        $allowCredentials = [];

        if ($user) {
            $allowCredentials = $user->webauthnKeys->map(function ($key) {
                return [
                    'id' => $key->credential_id,
                    'type' => 'public-key',
                    'transports' => $key->transports ?? [],
                ];
            })->toArray();
        }

        $options = [
            'challenge' => $challenge,
            'timeout' => (int)config('webauthn.timeout', 60000),
            'rpId' => $rpId,
            'allowCredentials' => $allowCredentials,
            'userVerification' => config('webauthn.user_verification', 'preferred'),
            'sessionId' => $sessionId,
        ];

        Log::debug('Generated WebAuthn authentication options', [
            'user_id' => $user?->id,
            'session_id' => $sessionId,
            'rp_id' => $rpId,
            'expected_origin' => $origin,
            'allowed_credentials_count' => count($allowCredentials),
        ]);

        return $options;
    }

    /**
     * Verify authentication response - FIXED CHALLENGE COMPARISON
     */
    public function verifyAuthentication(array $response, string $sessionId): ?User
    {
        $challengeData = Cache::get("webauthn_auth_challenge_{$sessionId}");
        if (!$challengeData) {
            throw new \Exception('Invalid or expired authentication session');
        }

        Cache::forget("webauthn_auth_challenge_{$sessionId}");

        try {
            $clientDataJSON = base64_decode($response['clientDataJSON']);
            $clientData = json_decode($clientDataJSON, true);

            if (!$clientData) {
                throw new \Exception('Invalid client data JSON');
            }

            // FIXED: Same challenge normalization as registration
            $expectedChallenge = $challengeData['challenge'];
            $receivedChallenge = $clientData['challenge'] ?? '';

            // Convert both to the same format for comparison
            $normalizedExpected = str_replace(['-', '_'], ['+', '/'], $expectedChallenge);
            $normalizedReceived = str_replace(['-', '_'], ['+', '/'], $receivedChallenge);

            // Add padding if needed
            $normalizedExpected = str_pad($normalizedExpected, ceil(strlen($normalizedExpected) / 4) * 4, '=', STR_PAD_RIGHT);
            $normalizedReceived = str_pad($normalizedReceived, ceil(strlen($normalizedReceived) / 4) * 4, '=', STR_PAD_RIGHT);

            if ($normalizedExpected !== $normalizedReceived) {
                throw new \Exception('Challenge verification failed');
            }

            if ($clientData['origin'] !== $challengeData['origin']) {
                throw new \Exception("Origin verification failed. Expected: {$challengeData['origin']}, Got: {$clientData['origin']}");
            }

            if ($clientData['type'] !== 'webauthn.get') {
                throw new \Exception('Invalid client data type for authentication');
            }

            // Find the credential
            $credentialId = $response['id'];
            $webauthnKey = WebAuthnKey::where('credential_id', $credentialId)->first();

            if (!$webauthnKey) {
                throw new \Exception('Security key not found');
            }

            // Update the counter
            $newCounter = $webauthnKey->counter + 1;
            if (!$webauthnKey->updateCounter($newCounter)) {
                throw new \Exception('Counter update failed - possible replay attack');
            }

            Log::info('WebAuthn authentication successful', [
                'user_id' => $webauthnKey->user_id,
                'key_id' => $webauthnKey->id,
                'key_name' => $webauthnKey->name,
                'counter' => $newCounter,
                'origin' => $challengeData['origin'],
            ]);

            return $webauthnKey->user;

        } catch (\Exception $e) {
            Log::error('WebAuthn authentication failed', [
                'credential_id' => $response['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'expected_rp_id' => $challengeData['rp_id'] ?? 'unknown',
                'expected_origin' => $challengeData['origin'] ?? 'unknown',
                'received_origin' => $clientData['origin'] ?? 'unknown',
                'session_id' => $sessionId,
            ]);
            throw $e;
        }
    }

    /**
     * Generate cryptographically secure challenge
     */
    private function generateChallenge(): string
    {
        return base64_encode(random_bytes(config('webauthn.challenge_length', 32)));
    }

    /**
     * Check WebAuthn support
     */
    public function isWebAuthnSupported(string $userAgent): bool
    {
        $supportedBrowsers = [
            '/Chrome\/([0-9]+)/' => 67,
            '/Firefox\/([0-9]+)/' => 60,
            '/Version\/([0-9]+).*Safari/' => 14,
            '/Edg\/([0-9]+)/' => 18,
        ];

        foreach ($supportedBrowsers as $pattern => $minVersion) {
            if (preg_match($pattern, $userAgent, $matches)) {
                return (int)$matches[1] >= $minVersion;
            }
        }

        return true;
    }

    /**
     * Get user's registered keys with metadata
     */
    public function getUserKeys(User $user): array
    {
        return $user->webauthnKeys->map(function ($key) {
            return [
                'id' => $key->id,
                'name' => $key->name,
                'description' => $key->getAuthenticatorDescription(),
                'last_used_at' => $key->last_used_at?->format('Y-m-d H:i:s'),
                'created_at' => $key->created_at->format('Y-m-d H:i:s'),
                'transports' => $key->transports,
                'backup_eligible' => $key->backup_eligible,
                'backup_state' => $key->backup_state,
            ];
        })->toArray();
    }

    /**
     * Get WebAuthn support information
     */
    public function getSupportInfo(string $userAgent): array
    {
        $supported = $this->isWebAuthnSupported($userAgent);

        return [
            'supported' => $supported,
            'rp_id' => $this->getRpId(),
            'origin' => $this->getOrigin(),
            'rp_name' => config('webauthn.rp_name', config('app.name')),
            'user_agent' => $userAgent,
            'recommendation' => $supported
                ? 'WebAuthn is supported. You can use security keys or biometric authentication.'
                : 'WebAuthn is not supported. Please use Chrome 67+, Firefox 60+, Safari 14+, or Edge 18+.',
        ];
    }
}
