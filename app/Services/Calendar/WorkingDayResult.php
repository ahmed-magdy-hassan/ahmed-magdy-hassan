<?php

declare(strict_types=1);

namespace App\Services\Calendar;

/**
 * Result returned by WorkingDayEvaluatorService::evaluate().
 *
 * Reason values:
 *   'working_day'   – normal working day
 *   'ramadan'       – working day with reduced hours (Ramadan)
 *   'weekend'       – falls on a country weekend
 *   'public_holiday' – falls on a public holiday
 */
final readonly class WorkingDayResult
{
    public function __construct(
        public readonly string  $date,
        public readonly bool    $isWorkingDay,
        public readonly int     $standardHours,

        /** One of: 'working_day' | 'ramadan' | 'weekend' | 'public_holiday'. */
        public readonly string  $reason,

        public readonly ?string $holidayName   = null,
        public readonly ?string $holidayNameAr = null,
    ) {}

    public function toArray(): array
    {
        $result = [
            'date'           => $this->date,
            'is_working_day' => $this->isWorkingDay,
            'standard_hours' => $this->standardHours,
            'reason'         => $this->reason,
        ];

        if ($this->holidayName !== null) {
            $result['holiday_name']    = $this->holidayName;
            $result['holiday_name_ar'] = $this->holidayNameAr;
        }

        return $result;
    }
}
