<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\ValueObjects\Calendar\RamadanPeriod;
use InvalidArgumentException;

/**
 * Provides Ramadan start/end dates and reduced-hours config for Egypt and
 * Saudi Arabia.
 *
 * Dates are astronomical predictions based on the Umm al-Qura calendar;
 * the authoritative start is set by official moon-sighting declarations in
 * each country, which may differ by ±1 day.
 *
 * Egyptian Labour Law 14/2025 (Art. 26) mandates 6 working hours/day during
 * Ramadan.  Saudi Labour Law (Art. 98) sets the same 6-hour cap for Muslim
 * employees.
 */
final class RamadanCalendarService
{
    public const int MIN_YEAR = 2025;
    public const int MAX_YEAR = 2027;

    /** Supported country codes. */
    private const array SUPPORTED_COUNTRIES = ['EG', 'SA'];

    /**
     * Astronomical Gregorian dates for Ramadan per year (same calendar for
     * both EG and SA — country-specific override is possible via $country
     * branching if official declarations diverge).
     *
     * Format: [year => [start, end]]
     */
    private const array RAMADAN_DATES = [
        2025 => ['2025-03-01', '2025-03-29'],
        2026 => ['2026-02-18', '2026-03-18'],
        2027 => ['2027-02-07', '2027-03-07'],
    ];

    public function forYear(int $year, string $country = 'EG'): RamadanPeriod
    {
        $country = strtoupper($country);

        if (!in_array($country, self::SUPPORTED_COUNTRIES, true)) {
            throw new InvalidArgumentException(
                "Unsupported country '{$country}'. Supported: " . implode(', ', self::SUPPORTED_COUNTRIES) . '.',
            );
        }

        if (!isset(self::RAMADAN_DATES[$year])) {
            throw new InvalidArgumentException(
                "Ramadan dates are pre-populated for year {$year}. Supported range: "
                . self::MIN_YEAR . '–' . self::MAX_YEAR . '.',
            );
        }

        [$start, $end] = self::RAMADAN_DATES[$year];

        return new RamadanPeriod(
            year: $year,
            country: $country,
            startDate: $start,
            endDate: $end,
            reducedDailyHours: RamadanPeriod::RAMADAN_DAILY_HOURS,
        );
    }

    /** Returns true if the given date falls within Ramadan for that country. */
    public function isRamadan(string $date, int $year, string $country = 'EG'): bool
    {
        return $this->forYear($year, $country)->includes($date);
    }
}
