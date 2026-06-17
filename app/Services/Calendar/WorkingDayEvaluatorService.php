<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\ValueObjects\Calendar\PublicHolidayCalendar;
use App\ValueObjects\Calendar\RamadanPeriod;
use Carbon\CarbonImmutable;

/**
 * Evaluates whether a date is a working day and how many standard hours
 * apply, given a country's holiday calendar and optional Ramadan period.
 *
 * Used by leave deduction logic (holidays excluded from leave balance) and
 * attendance evaluation (absent vs holiday vs normal day).
 */
final class WorkingDayEvaluatorService
{
    /**
     * @param  PublicHolidayCalendar $calendar Country calendar for the relevant year.
     * @param  RamadanPeriod|null    $ramadan  Ramadan period if active that year.
     */
    public function evaluate(
        string               $date,
        PublicHolidayCalendar $calendar,
        ?RamadanPeriod       $ramadan = null,
    ): WorkingDayResult {
        $d       = CarbonImmutable::parse($date);
        $weekend = $calendar->weekendDays();

        // Weekend check (0=Sun,1=Mon,...,5=Fri,6=Sat)
        if (in_array($d->dayOfWeek, $weekend, true)) {
            return new WorkingDayResult(
                date: $date,
                isWorkingDay: false,
                standardHours: 0,
                reason: 'weekend',
            );
        }

        // Public holiday check
        $holiday = $calendar->holidayOn($date);
        if ($holiday !== null) {
            return new WorkingDayResult(
                date: $date,
                isWorkingDay: false,
                standardHours: 0,
                reason: 'public_holiday',
                holidayName: $holiday->name,
                holidayNameAr: $holiday->nameAr,
            );
        }

        // Working day — apply Ramadan hours if applicable
        $hours = $ramadan?->dailyHours($date) ?? RamadanPeriod::STANDARD_DAILY_HOURS;
        $inRamadan = $ramadan?->includes($date) ?? false;

        return new WorkingDayResult(
            date: $date,
            isWorkingDay: true,
            standardHours: $hours,
            reason: $inRamadan ? 'ramadan' : 'working_day',
        );
    }

    /**
     * Count working days in the given month that are NOT holidays or weekends.
     */
    public function workingDaysInMonth(
        int                  $year,
        int                  $month,
        PublicHolidayCalendar $calendar,
    ): int {
        return $calendar->workingDaysInMonth($month);
    }
}
