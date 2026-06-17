<?php

declare(strict_types=1);

namespace App\Http\Requests\Hr\Egypt;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/hr/egypt/companies/{company}/payroll/run
 *
 * Runs monthly payroll for every employee in a company.
 * The caller supplies the payroll month and a list of employee wage inputs.
 * No DB writes occur — the response is a preview/report for confirmation before
 * payroll slips are generated.
 *
 * Example request body:
 * {
 *   "year": 2026,
 *   "month": 6,
 *   "tax_year": 2026,
 *   "employees": [
 *     {
 *       "employee_id": "EMP-001",
 *       "name": "Ahmed Ali",
 *       "national_id": "29001011234567",
 *       "nosi_number": "1234567890",
 *       "gross_salary": 12000.00
 *     }
 *   ]
 * }
 */
final class CompanyPayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year'                         => ['required', 'integer', 'min:2020', 'max:2035'],
            'month'                        => ['required', 'integer', 'min:1', 'max:12'],
            'tax_year'                     => ['sometimes', 'integer', 'min:2022', 'max:2035'],
            'employees'                    => ['required', 'array', 'min:1', 'max:10000'],
            'employees.*.employee_id'      => ['required', 'string', 'max:50'],
            'employees.*.name'             => ['required', 'string', 'max:200'],
            'employees.*.national_id'      => ['required', 'string', 'size:14'],
            'employees.*.nosi_number'      => ['required', 'string', 'max:20'],
            'employees.*.gross_salary'     => ['required', 'numeric', 'min:0'],
            'employees.*.insured_salary'   => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'employees.*.special_needs'    => ['sometimes', 'boolean'],
            'employees.*.hire_date'        => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'employees.required'                   => 'employees array is required.',
            'employees.min'                        => 'At least one employee is required.',
            'employees.*.national_id.size'         => 'Each employee national_id must be exactly 14 digits.',
            'employees.*.gross_salary.min'         => 'gross_salary cannot be negative.',
        ];
    }
}
