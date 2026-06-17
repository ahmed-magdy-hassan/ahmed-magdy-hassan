<?php

declare(strict_types=1);

namespace App\Http\Requests\Hr\Egypt;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query parameters for GET /api/hr/egypt/companies/{company}/payroll/nosi-report
 *
 * Example:
 *   GET /api/hr/egypt/companies/42/payroll/nosi-report?year=2026&month=6
 */
final class NosiMonthlyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year'  => ['required', 'integer', 'min:2022', 'max:2035'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }

    public function messages(): array
    {
        return [
            'year.required'  => 'Year is required (e.g. ?year=2026).',
            'month.required' => 'Month is required as a number 1–12 (e.g. ?month=6).',
            'month.min'      => 'Month must be between 1 and 12.',
            'month.max'      => 'Month must be between 1 and 12.',
        ];
    }
}
