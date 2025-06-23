<?php
// app/Http/Requests/WebAuthnOptionsRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebAuthnOptionsRequest extends FormRequest
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
            'email' => 'nullable|email|exists:users,email',
            'userVerification' => 'nullable|string|in:required,preferred,discouraged',
            'authenticatorAttachment' => 'nullable|string|in:platform,cross-platform',
            'residentKey' => 'nullable|string|in:required,preferred,discouraged',
            'timeout' => 'nullable|integer|min:30000|max:300000', // 30 seconds to 5 minutes
        ];
    }

    /**
     * Get the validation messages for the defined rules.
     */
    public function messages(): array
    {
        return [
            'email.email' => 'Please provide a valid email address.',
            'email.exists' => 'No user found with this email address.',
            'userVerification.in' => 'User verification must be: required, preferred, or discouraged.',
            'authenticatorAttachment.in' => 'Authenticator attachment must be: platform or cross-platform.',
            'residentKey.in' => 'Resident key requirement must be: required, preferred, or discouraged.',
            'timeout.integer' => 'Timeout must be a number.',
            'timeout.min' => 'Timeout must be at least 30 seconds (30000 milliseconds).',
            'timeout.max' => 'Timeout cannot exceed 5 minutes (300000 milliseconds).',
        ];
    }

    /**
     * Get the body parameters for Scribe API documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => 'User email for account-specific authentication (optional for passwordless)',
                'example' => 'user@example.com',
            ],
            'userVerification' => [
                'description' => 'Requirement for user verification (biometrics, PIN, etc.)',
                'example' => 'preferred',
            ],
            'authenticatorAttachment' => [
                'description' => 'Preference for built-in vs external authenticators',
                'example' => 'cross-platform',
            ],
            'residentKey' => [
                'description' => 'Whether to create a resident (discoverable) credential',
                'example' => 'preferred',
            ],
            'timeout' => [
                'description' => 'Timeout in milliseconds for the authentication ceremony',
                'example' => 60000,
            ],
        ];
    }

    /**
     * Get the default values for options
     */
    public function getOptionsWithDefaults(): array
    {
        return [
            'userVerification' => $this->input('userVerification', 'preferred'),
            'authenticatorAttachment' => $this->input('authenticatorAttachment'),
            'residentKey' => $this->input('residentKey', 'preferred'),
            'timeout' => $this->input('timeout', 60000),
        ];
    }

    /**
     * Check if this is a passwordless authentication request
     */
    public function isPasswordless(): bool
    {
        return !$this->filled('email');
    }

    /**
     * Get the user email if provided
     */
    public function getUserEmail(): ?string
    {
        return $this->input('email');
    }
}
