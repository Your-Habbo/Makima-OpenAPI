<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => 'required|string',
            'password' => 'required|string',
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
            'login' => [
                'description' => 'The user\'s email address or username',
                'example' => 'user@example.com',
            ],
            'password' => [
                'description' => 'The user\'s password',
                'example' => 'password123',
            ],
            'device_name' => [
                'description' => 'Device name for the token (used to identify the session)',
                'example' => 'iPhone 12',
            ],
        ];
    }

    public function getCredentials(): array
    {
        $login = $this->input('login');
        
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        
        return [
            $field => $login,
            'password' => $this->input('password'),
        ];
    }
}