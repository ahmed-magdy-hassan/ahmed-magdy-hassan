<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the 2FA login challenge.
 * The user must supply exactly one of: a TOTP code or a recovery code.
 */
final class TwoFactorChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'          => ['required_without:recovery_code', 'nullable', 'string', 'digits:6'],
            'recovery_code' => ['required_without:code',          'nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required_without'          => 'Supply either a 6-digit TOTP code or a recovery code.',
            'recovery_code.required_without'  => 'Supply either a 6-digit TOTP code or a recovery code.',
            'code.digits'                     => 'The TOTP code must be exactly 6 digits.',
        ];
    }
}
