<?php
// app/Http/Controllers/Api/Auth/WebAuthnController.php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebAuthnAuthenticateRequest;
use App\Http\Requests\WebAuthnKeyManagementRequest;
use App\Http\Requests\WebAuthnOptionsRequest;
use App\Http\Requests\WebAuthnRegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\WebAuthnKey;
use App\Services\WebAuthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WebAuthnController extends Controller
{
    public function __construct(
        private WebAuthnService $webauthnService
    ) {}

    /**
     * Check WebAuthn Support
     */
    public function supported(Request $request): JsonResponse
    {
        $userAgent = $request->userAgent() ?? '';
        $supportInfo = $this->webauthnService->getSupportInfo($userAgent);

        return response()->json($supportInfo);
    }

    /**
     * Get Registration Options
     */
    public function registrationOptions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->webauthnService->isWebAuthnSupported($request->userAgent() ?? '')) {
            return response()->json([
                'message' => 'WebAuthn is not supported by your browser.',
                'supported' => false,
            ], 400);
        }

        try {
            $options = $this->webauthnService->generateRegistrationOptions($user);

            return response()->json([
                'options' => $options,
                'message' => 'WebAuthn registration options generated successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate WebAuthn registration options', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to generate registration options.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Register WebAuthn Key
     */
    public function register(WebAuthnRegisterRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->webauthnKeys()->count() >= 10) {
            return response()->json([
                'message' => 'Maximum number of security keys reached.',
                'max_keys' => 10,
                'current_count' => $user->webauthnKeys()->count(),
            ], 400);
        }

        $response = $request->getCredentialData();
        $keyName = $request->input('name');
        $sessionId = $request->input('sessionId');

        if (!$sessionId) {
            return response()->json([
                'message' => 'Session ID is required.',
            ], 400);
        }

        try {
            $webauthnKey = $this->webauthnService->verifyRegistration(
                $user,
                array_merge($response, ['sessionId' => $sessionId]),
                $keyName
            );

            if (!$webauthnKey) {
                throw new \Exception('Registration verification returned null');
            }

            return response()->json([
                'message' => 'WebAuthn key registered successfully.',
                'key' => [
                    'id' => $webauthnKey->id,
                    'name' => $webauthnKey->name,
                    'description' => $webauthnKey->getAuthenticatorDescription(),
                    'transports' => $webauthnKey->transports,
                    'created_at' => $webauthnKey->created_at->toDateTimeString(),
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('WebAuthn registration failed', [
                'user_id' => $user->id,
                'key_name' => $keyName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw ValidationException::withMessages([
                'registration' => ['Failed to register WebAuthn key: ' . $e->getMessage()],
            ]);
        }
    }

    /**
     * Get Authentication Options
     */
    public function authenticationOptions(WebAuthnOptionsRequest $request): JsonResponse
    {
        $email = $request->getUserEmail();
        $user = $email ? User::where('email', $email)->first() : null;

        if (!$this->webauthnService->isWebAuthnSupported($request->userAgent() ?? '')) {
            return response()->json([
                'message' => 'WebAuthn is not supported by your browser.',
                'supported' => false,
            ], 400);
        }

        try {
            $options = $this->webauthnService->generateAuthenticationOptions($user);

            return response()->json([
                'options' => $options,
                'message' => 'WebAuthn authentication options generated successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate WebAuthn authentication options', [
                'user_email' => $email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to generate authentication options.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Authenticate with WebAuthn
     */
    public function authenticate(WebAuthnAuthenticateRequest $request): JsonResponse
    {
        $response = $request->getAssertionData();
        $sessionId = $request->getSessionId();
        $deviceName = $request->getDeviceName();

        try {
            $user = $this->webauthnService->verifyAuthentication($response, $sessionId);

            if (!$user) {
                throw new \Exception('Authentication verification failed');
            }

            if (!$user->is_active) {
                throw ValidationException::withMessages([
                    'authentication' => ['Your account has been deactivated.'],
                ]);
            }

            // Update last login info
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            // Create access token
            $token = $user->createToken($deviceName)->plainTextToken;

            Log::info('WebAuthn authentication successful', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'device_name' => $deviceName,
            ]);

            return response()->json([
                'user' => new UserResource($user->load('profile', 'roles')),
                'token' => $token,
                'message' => 'WebAuthn authentication successful.',
            ]);

        } catch (\Throwable $e) {
            Log::error('WebAuthn authentication failed', [
                'session_id' => $sessionId,
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'authentication' => ['WebAuthn authentication failed: ' . $e->getMessage()],
            ]);
        }
    }

    /**
     * List User's WebAuthn Keys
     */
    public function keys(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $keys = $this->webauthnService->getUserKeys($user);

            return response()->json([
                'keys' => $keys,
                'count' => count($keys),
                'max_keys' => 10,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve WebAuthn keys', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to retrieve security keys.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete WebAuthn Key
     */
    public function deleteKey(Request $request, int $keyId): JsonResponse
    {
        $user = $request->user();

        $webauthnKey = WebAuthnKey::where('id', $keyId)
            ->where('user_id', $user->id)
            ->first();

        if (!$webauthnKey) {
            return response()->json([
                'message' => 'WebAuthn key not found.',
            ], 404);
        }

        // Prevent deletion of the last key if user has no other 2FA methods
        $userKeyCount = $user->webauthnKeys()->count();
        $hasTwoFactor = $user->two_factor_enabled;

        if ($userKeyCount === 1 && !$hasTwoFactor) {
            return response()->json([
                'message' => 'Cannot delete the last security key. Please enable another 2FA method first.',
                'suggestion' => 'Enable TOTP authentication before removing your last security key.',
            ], 400);
        }

        $keyName = $webauthnKey->name;
        $webauthnKey->delete();

        Log::info('WebAuthn key deleted', [
            'user_id' => $user->id,
            'key_id' => $keyId,
            'key_name' => $keyName,
        ]);

        return response()->json([
            'message' => 'WebAuthn key deleted successfully.',
            'deleted_key' => $keyName,
        ]);
    }

    /**
     * Update WebAuthn Key Name
     */
    public function updateKeyName(WebAuthnKeyManagementRequest $request, int $keyId): JsonResponse
    {
        $user = $request->user();

        $webauthnKey = WebAuthnKey::where('id', $keyId)
            ->where('user_id', $user->id)
            ->first();

        if (!$webauthnKey) {
            return response()->json([
                'message' => 'WebAuthn key not found.',
            ], 404);
        }

        $newName = $request->getNewName();
        $oldName = $webauthnKey->name;

        $webauthnKey->update([
            'name' => $newName,
        ]);

        Log::info('WebAuthn key name updated', [
            'user_id' => $user->id,
            'key_id' => $keyId,
            'old_name' => $oldName,
            'new_name' => $newName,
        ]);

        return response()->json([
            'message' => 'WebAuthn key name updated successfully.',
            'key' => [
                'id' => $webauthnKey->id,
                'name' => $webauthnKey->name,
                'description' => $webauthnKey->getAuthenticatorDescription(),
                'transports' => $webauthnKey->transports,
            ],
        ]);
    }
}
