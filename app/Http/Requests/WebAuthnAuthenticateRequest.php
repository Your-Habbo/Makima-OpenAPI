<?php
// app/Http/Requests/WebAuthnAuthenticateRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebAuthnAuthenticateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'id' => 'required|string',
            'rawId' => 'required|string',
            'type' => 'required|string|in:public-key',
            'clientDataJSON' => 'required|string',
            'authenticatorData' => 'required|string',
            'signature' => 'required|string',
            'userHandle' => 'nullable|string',
            'sessionId' => 'required|string|size:32',
            'device_name' => 'required|string|max:255',
        ];
    }

    /**
     * Get the validation messages for the defined rules.
     */
    public function messages(): array
    {
        return [
            'id.required' => 'The credential ID is required.',
            'rawId.required' => 'The raw credential ID is required.',
            'type.required' => 'The credential type is required.',
            'type.in' => 'The credential type must be "public-key".',
            'clientDataJSON.required' => 'The client data is required.',
            'authenticatorData.required' => 'The authenticator data is required.',
            'signature.required' => 'The authentication signature is required.',
            'sessionId.required' => 'The session ID is required.',
            'sessionId.size' => 'The session ID must be exactly 32 characters.',
            'device_name.required' => 'A device name is required for token identification.',
            'device_name.max' => 'The device name cannot be longer than 255 characters.',
        ];
    }

    /**
     * Get the body parameters for Scribe API documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'id' => [
                'description' => 'Base64-encoded credential ID from the WebAuthn response',
                'example' => 'ATdlvG7vBn...',
            ],
            'rawId' => [
                'description' => 'Base64-encoded raw credential ID from the WebAuthn response',
                'example' => 'ATdlvG7vBn...',
            ],
            'type' => [
                'description' => 'The credential type, always "public-key" for WebAuthn',
                'example' => 'public-key',
            ],
            'clientDataJSON' => [
                'description' => 'Base64-encoded client data JSON from the WebAuthn response',
                'example' => 'eyJ0eXBlIjoid2ViYXV...',
            ],
            'authenticatorData' => [
                'description' => 'Base64-encoded authenticator data from the WebAuthn response',
                'example' => 'SZYN5YgOjGh0NBcPZHZgW4...',
            ],
            'signature' => [
                'description' => 'Base64-encoded signature from the WebAuthn response',
                'example' => 'MEYCIQDTGOe7jCOLw5fJ...',
            ],
            'userHandle' => [
                'description' => 'Base64-encoded user handle (optional, used for resident keys)',
                'example' => 'MQ==',
            ],
            'sessionId' => [
                'description' => 'Session ID from the authentication options request',
                'example' => 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6',
            ],
            'device_name' => [
                'description' => 'Device name for the access token (used to identify the session)',
                'example' => 'Chrome on MacBook Pro',
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure device_name is trimmed and properly formatted
        if ($this->has('device_name')) {
            $this->merge([
                'device_name' => trim($this->input('device_name')),
            ]);
        }
    }

    /**
     * Get the WebAuthn assertion data formatted for processing
     */
    public function getAssertionData(): array
    {
        return [
            'id' => $this->input('id'),
            'rawId' => $this->input('rawId'),
            'type' => $this->input('type'),
            'clientDataJSON' => $this->input('clientDataJSON'),
            'authenticatorData' => $this->input('authenticatorData'),
            'signature' => $this->input('signature'),
            'userHandle' => $this->input('userHandle'),
        ];
    }

    /**
     * Get the session ID for challenge verification
     */
    public function getSessionId(): string
    {
        return $this->input('sessionId');
    }

    /**
     * Get the device name for token creation
     */
    public function getDeviceName(): string
    {
        return $this->input('device_name');
    }
}
