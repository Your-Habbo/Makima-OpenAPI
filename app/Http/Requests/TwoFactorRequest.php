<?php
//app/Http/Requests/TwoFactorRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The 'code' is only required if 'recovery_code' is not present.
            // It must be a 6-digit numeric string if provided.
            'code' => 'required_without:recovery_code|nullable|string|size:6|regex:/^[0-9]+$/',

            // The 'recovery_code' is only required if 'code' is not present.
            // It must be a string if provided.
            'recovery_code' => 'required_without:code|nullable|string',
        ];
    }

    /**
     * Get the body parameters for Scribe API documentation.
     *
     * @return array
     */
    public function bodyParameters(): array
    {
        return [
            'code' => [
                'description' => 'The 6-digit OTP code from your authenticator app. Use this OR the recovery_code.',
                'example' => '123456',
            ],
            'recovery_code' => [
                'description' => 'One of your 2FA recovery codes. Use this OR the code.',
                'example' => 'abcde-fghij',
            ],
        ];
    }

    /**
     * Get the custom validation messages for the defined rules.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'The code must contain only numbers.',
            'code.size' => 'The code must be exactly 6 digits.',
        ];
    }
}
