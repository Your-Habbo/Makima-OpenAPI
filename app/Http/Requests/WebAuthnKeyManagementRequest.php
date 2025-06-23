<?php
// app/Http/Requests/WebAuthnKeyManagementRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebAuthnKeyManagementRequest extends FormRequest
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
        $rules = [];

        // Rules for updating key name
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = 'required|string|max:255|regex:/^[a-zA-Z0-9\s\-_]+$/';
        }

        // Rules for bulk operations
        if ($this->has('key_ids')) {
            $rules['key_ids'] = 'required|array|min:1|max:10';
            $rules['key_ids.*'] = 'integer|exists:webauthn_keys,id';
        }

        // Rules for export/backup operations
        if ($this->has('include_metadata')) {
            $rules['include_metadata'] = 'boolean';
        }

        return $rules;
    }

    /**
     * Get the validation messages for the defined rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A name for your security key is required.',
            'name.regex' => 'The name can only contain letters, numbers, spaces, hyphens, and underscores.',
            'name.max' => 'The name cannot be longer than 255 characters.',
            'key_ids.required' => 'At least one key must be selected.',
            'key_ids.array' => 'Key IDs must be provided as an array.',
            'key_ids.min' => 'At least one key must be selected.',
            'key_ids.max' => 'Cannot select more than 10 keys at once.',
            'key_ids.*.integer' => 'Each key ID must be a valid number.',
            'key_ids.*.exists' => 'One or more selected keys do not exist.',
            'include_metadata.boolean' => 'Include metadata must be true or false.',
        ];
    }

    /**
     * Get the body parameters for Scribe API documentation.
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'New name for the security key',
                'example' => 'Updated YubiKey Name',
            ],
            'key_ids' => [
                'description' => 'Array of WebAuthn key IDs for bulk operations',
                'example' => [1, 2, 3],
            ],
            'include_metadata' => [
                'description' => 'Whether to include metadata in export operations',
                'example' => true,
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure name is trimmed and properly formatted
        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->input('name')),
            ]);
        }

        // Convert string boolean values to actual booleans
        if ($this->has('include_metadata')) {
            $this->merge([
                'include_metadata' => filter_var($this->input('include_metadata'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    /**
     * Get the new name for the key
     */
    public function getNewName(): string
    {
        return $this->input('name');
    }

    /**
     * Get the array of key IDs for bulk operations
     */
    public function getKeyIds(): array
    {
        return $this->input('key_ids', []);
    }

    /**
     * Check if metadata should be included in operations
     */
    public function shouldIncludeMetadata(): bool
    {
        return $this->input('include_metadata', false);
    }
}
