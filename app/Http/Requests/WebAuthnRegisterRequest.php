<?php
// app/Http/Requests/WebAuthnRegisterRequest.php - Fix transport validation

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebAuthnRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                     => 'required|string|max:255|regex:/^[a-zA-Z0-9\s\-_]+$/',
            'id'                       => 'required|string',
            'rawId'                    => 'required|string',
            'type'                     => 'required|string|in:public-key',
            'clientDataJSON'           => 'required|string',
            'attestationObject'        => 'required|string',
            'authenticatorAttachment'  => 'nullable|string|in:platform,cross-platform',
            'transports'               => 'nullable|array|max:10',
            // Fix: Allow all possible transport values including 'hybrid'
            'transports.*'             => 'string|in:usb,nfc,ble,internal,hybrid,smart-card',
            'aaguid'                   => 'nullable|string',
            'backupEligible'           => 'nullable|boolean',
            'backupState'              => 'nullable|boolean',
            'sessionId'                => 'required|string|size:32', // Add sessionId validation
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'A name for your security key is required.',
            'name.regex'       => 'The name can only contain letters, numbers, spaces, hyphens, and underscores.',
            'name.max'         => 'The name cannot be longer than 255 characters.',
            'id.required'      => 'The credential ID is required.',
            'rawId.required'   => 'The raw credential ID is required.',
            'type.required'    => 'The credential type is required.',
            'type.in'          => 'The credential type must be "public-key".',
            'clientDataJSON.required'    => 'The client data is required.',
            'attestationObject.required' => 'The attestation object is required.',
            'authenticatorAttachment.in' => 'Invalid authenticator attachment type.',
            'transports.array'           => 'Transports must be an array.',
            'transports.max'             => 'Too many transport methods specified.',
            'transports.*.in'            => 'Invalid transport method. Allowed: usb, nfc, ble, internal, hybrid, smart-card.',
            'sessionId.required'         => 'Session ID is required.',
            'sessionId.size'             => 'Invalid session ID format.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->input('name')),
            ]);
        }

        if ($this->has('backupEligible')) {
            $this->merge([
                'backupEligible' => filter_var(
                    $this->input('backupEligible'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ),
            ]);
        }

        if ($this->has('backupState')) {
            $this->merge([
                'backupState' => filter_var(
                    $this->input('backupState'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ),
            ]);
        }

        // Clean up transports array
        if ($this->has('transports') && is_array($this->input('transports'))) {
            $validTransports = ['usb', 'nfc', 'ble', 'internal', 'hybrid', 'smart-card'];
            $transports = array_filter(
                $this->input('transports'),
                fn($transport) => in_array($transport, $validTransports)
            );
            $this->merge(['transports' => array_values($transports)]);
        }
    }

    public function getCredentialData(): array
    {
        return [
            'id'                      => $this->input('id'),
            'rawId'                   => $this->input('rawId'),
            'type'                    => $this->input('type'),
            'clientDataJSON'          => $this->input('clientDataJSON'),
            'attestationObject'       => $this->input('attestationObject'),
            'authenticatorAttachment' => $this->input('authenticatorAttachment'),
            'transports'              => $this->input('transports', []),
            'aaguid'                  => $this->input('aaguid'),
            'backupEligible'          => $this->input('backupEligible', false),
            'backupState'             => $this->input('backupState', false),
        ];
    }
}
