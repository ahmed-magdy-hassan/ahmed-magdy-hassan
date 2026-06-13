<?php

declare(strict_types=1);

namespace App\Http\Requests\Hr\Egypt;

use Illuminate\Foundation\Http\FormRequest;

final class EgyptPayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gross_salary'      => ['required', 'numeric', 'min:0'],
            'company_headcount' => ['sometimes', 'integer', 'min:1'],
            'tax_year'          => ['sometimes', 'integer', 'min:2022', 'max:2030'],
        ];
    }

    public function messages(): array
    {
        return [
            'gross_salary.required' => 'gross_salary (monthly EGP amount) is required.',
            'gross_salary.min'      => 'gross_salary cannot be negative.',
        ];
    }
}
