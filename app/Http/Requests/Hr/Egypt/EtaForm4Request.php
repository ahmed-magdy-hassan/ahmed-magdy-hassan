<?php

declare(strict_types=1);

namespace App\Http\Requests\Hr\Egypt;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query parameters for GET /api/hr/egypt/companies/{company}/payroll/eta-form4
 *
 * Example:
 *   GET /api/hr/egypt/companies/42/payroll/eta-form4?year=2026&quarter=2
 */
final class EtaForm4Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year'    => ['required', 'integer', 'min:2022', 'max:2035'],
            'quarter' => ['required', 'integer', 'min:1', 'max:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'year.required'    => 'Tax year is required (e.g. ?year=2026).',
            'quarter.required' => 'Quarter is required: 1 (Jan–Mar), 2 (Apr–Jun), 3 (Jul–Sep), 4 (Oct–Dec).',
            'quarter.min'      => 'Quarter must be between 1 and 4.',
            'quarter.max'      => 'Quarter must be between 1 and 4.',
        ];
    }
}
