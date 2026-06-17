<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the TOTP code supplied for confirming enrolment, disabling 2FA,
 * or regenerating recovery codes — any action that requires the current code.
 */
final class TwoFactorConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'A 6-digit TOTP code is required.',
            'code.digits'   => 'The code must be exactly 6 digits.',
        ];
    }
}
