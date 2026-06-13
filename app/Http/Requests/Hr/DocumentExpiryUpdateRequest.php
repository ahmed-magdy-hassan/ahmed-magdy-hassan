<?php

declare(strict_types=1);

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

final class DocumentExpiryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'iqama_expiry_date'       => ['nullable', 'date'],
            'passport_expiry_date'    => ['nullable', 'date'],
            'work_visa_expiry_date'   => ['nullable', 'date'],
            'work_permit_expiry_date' => ['nullable', 'date'],
            'contract_end_date'       => ['nullable', 'date'],
        ];
    }
}
