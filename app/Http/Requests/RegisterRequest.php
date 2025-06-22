<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username|min:3|max:50|regex:/^[a-zA-Z0-9._-]+$/',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'device_name' => 'required|string|max:255',
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
            'name' => [
                'description' => 'The user\'s full name',
                'example' => 'John Doe',
            ],
            'username' => [
                'description' => 'Unique username (3-50 characters, letters, numbers, dots, underscores, hyphens only)',
                'example' => 'johndoe',
            ],
            'email' => [
                'description' => 'The user\'s email address',
                'example' => 'john@example.com',
            ],
            'password' => [
                'description' => 'Password (minimum 8 characters)',
                'example' => 'password123',
            ],
            'password_confirmation' => [
                'description' => 'Password confirmation (must match password)',
                'example' => 'password123',
            ],
            'device_name' => [
                'description' => 'Device name for the token',
                'example' => 'iPhone 12',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'Username can only contain letters, numbers, dots, underscores, and hyphens.',
        ];
    }
}