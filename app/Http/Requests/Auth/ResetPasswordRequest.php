<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token'                 => ['required', 'string'],
            'email'                 => ['required', 'email:rfc,dns'],
            'password'              => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'password_confirmation' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required'    => 'A password reset token is required.',
            'email.required'    => 'The email address associated with the token is required.',
            'password.required' => 'A new password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
