<?php

declare(strict_types=1);

namespace App\Http\Requests\Hr;

use App\Enums\Payroll\TerminationReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class EosbCalculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'end_date'           => ['required', 'date', 'after_or_equal:today'],
            'termination_reason' => ['required', new Enum(TerminationReason::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'termination_reason.Illuminate\\Validation\\Rules\\Enum' =>
                'termination_reason must be one of: ' . implode(', ', array_column(TerminationReason::cases(), 'value')),
        ];
    }
}
