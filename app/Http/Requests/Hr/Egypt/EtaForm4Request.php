<?php

declare(strict_types=1);

namespace App\Http\Requests\Hr\Egypt;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates query-string parameters for the ETA Form 4 quarterly report endpoint.
 *
 * Route: GET /api/hr/egypt/companies/{company}/payroll/eta-form4?year=2026&quarter=2
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
            'year'    => ['required', 'integer', 'min:2022', 'max:2030'],
            'quarter' => ['required', 'integer', 'min:1',    'max:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'year.required'    => 'The tax year is required (e.g. year=2026).',
            'year.integer'     => 'The tax year must be an integer.',
            'year.min'         => 'ETA Form 4 data is available from 2022 onwards.',
            'year.max'         => 'The tax year cannot exceed 2030.',
            'quarter.required' => 'The quarter is required (1–4).',
            'quarter.integer'  => 'The quarter must be an integer.',
            'quarter.min'      => 'Quarter must be between 1 and 4.',
            'quarter.max'      => 'Quarter must be between 1 and 4.',
        ];
    }

    public function year(): int
    {
        return (int) $this->validated('year');
    }

    public function quarter(): int
    {
        return (int) $this->validated('quarter');
    }
}
