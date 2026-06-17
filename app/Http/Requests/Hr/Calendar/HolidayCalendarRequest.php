<?php

declare(strict_types=1);

namespace App\Http\Requests\Hr\Calendar;

use Illuminate\Foundation\Http\FormRequest;

final class HolidayCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2025', 'max:2027'],
        ];
    }
}
