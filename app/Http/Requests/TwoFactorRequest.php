<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|size:6|regex:/^[0-9]+$/',
        ];
    }

    /**
     * Get the body parameters for the request.
     *
     * @return array
     */
    public function bodyParameters(): array
    {
        return [
            'code' => [
                'description' => 'The 6-digit OTP code from your authenticator app',
                'example' => '123456',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'The code must contain only numbers.',
            'code.size' => 'The code must be exactly 6 digits.',
        ];
    }
}