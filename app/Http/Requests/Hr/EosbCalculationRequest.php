<?php

declare(strict_types=1);

namespace App\Http\Requests\Hr;

use App\Enums\Payroll\TerminationReason;
use App\Services\Calendar\HijriDateService;
use App\ValueObjects\HijriDate;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class EosbCalculationRequest extends FormRequest
{
    /** Regex for a Hijri date string: YYYY-MM-DD, month 01-12, day 01-30. */
    private const HIJRI_REGEX = '/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|30)$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Gregorian end date — required unless the caller supplies end_date_hijri.
            'end_date'       => ['required_without:end_date_hijri', 'nullable', 'date', 'after_or_equal:today'],

            // Hijri end date (YYYY-MM-DD, Islamic calendar) — optional override.
            'end_date_hijri' => ['required_without:end_date', 'nullable', 'regex:' . self::HIJRI_REGEX],

            // Optional Hijri override for the employee's hire date (overrides employee.hire_date).
            'hire_date_hijri' => ['nullable', 'regex:' . self::HIJRI_REGEX],

            'termination_reason' => ['required', new Enum(TerminationReason::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.required_without'       => 'Provide either end_date (Gregorian) or end_date_hijri.',
            'end_date_hijri.required_without' => 'Provide either end_date (Gregorian) or end_date_hijri.',
            'end_date_hijri.regex'            => 'end_date_hijri must be a valid Hijri date in YYYY-MM-DD format (month 01-12, day 01-30).',
            'hire_date_hijri.regex'           => 'hire_date_hijri must be a valid Hijri date in YYYY-MM-DD format (month 01-12, day 01-30).',
            'termination_reason.Illuminate\\Validation\\Rules\\Enum' =>
                'termination_reason must be one of: ' . implode(', ', array_column(TerminationReason::cases(), 'value')),
        ];
    }

    /**
     * Resolve the effective end date as a Carbon instance.
     * If end_date_hijri is present it takes precedence over end_date.
     */
    public function resolvedEndDate(): Carbon
    {
        $hijri = $this->validated('end_date_hijri');

        if ($hijri !== null) {
            return app(HijriDateService::class)->toGregorian(HijriDate::parse($hijri));
        }

        return Carbon::parse($this->validated('end_date'));
    }

    /**
     * Resolve an optional Hijri hire-date override to a Carbon instance, or null.
     */
    public function resolvedHireDateOverride(): ?Carbon
    {
        $hijri = $this->validated('hire_date_hijri');

        if ($hijri === null) {
            return null;
        }

        return app(HijriDateService::class)->toGregorian(HijriDate::parse($hijri));
    }
}
