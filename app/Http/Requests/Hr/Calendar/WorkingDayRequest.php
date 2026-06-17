<?php

declare(strict_types=1);

namespace App\Http\Requests\Hr\Calendar;

use Illuminate\Foundation\Http\FormRequest;

final class WorkingDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
