<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\ValueObjects\Calendar\PublicHoliday;
use App\ValueObjects\Calendar\PublicHolidayCalendar;
use InvalidArgumentException;

/**
 * Provides Saudi Arabia's official public-holiday calendar for a given
 * Gregorian year.
 *
 * Fixed national holidays are sourced from the Saudi Human Resources Ministry.
 * Islamic-based holidays use astronomical estimates and may shift ±1 day on
 * official moon-sighting declarations.
 */
final class SaudiHolidayCalendarService
{
    public const int MIN_YEAR = 2025;
    public const int MAX_YEAR = 2027;

    public function forYear(int $year): PublicHolidayCalendar
    {
        if ($year < self::MIN_YEAR || $year > self::MAX_YEAR) {
            throw new InvalidArgumentException(
                "Saudi holiday calendar is pre-populated for {$year}. Supported range: "
                . self::MIN_YEAR . '–' . self::MAX_YEAR . '.',
            );
        }

        return new PublicHolidayCalendar('SA', $year, $this->holidays($year));
    }

    /** @return PublicHoliday[] */
    private function holidays(int $year): array
    {
        return [
            ...$this->fixedHolidays($year),
            ...$this->islamicHolidays($year),
        ];
    }

    /** @return PublicHoliday[] */
    private function fixedHolidays(int $year): array
    {
        return [
            new PublicHoliday(
                date: "{$year}-02-22",
                name: 'Saudi Founding Day',
                nameAr: 'يوم التأسيس السعودي',
                type: 'national',
                daysOff: 1,
            ),
            new PublicHoliday(
                date: "{$year}-09-23",
                name: 'Saudi National Day',
                nameAr: 'اليوم الوطني السعودي',
                type: 'national',
                daysOff: 1,
            ),
        ];
    }

    /**
     * Islamic-based holidays: same Hijri event dates as Egypt but Saudi
     * typically grants the same duration by Royal Decree.
     * Eid al-Fitr: 3 days; Eid al-Adha: 4 days.
     *
     * @return PublicHoliday[]
     */
    private function islamicHolidays(int $year): array
    {
        $data = [
            2025 => [
                ['2025-03-30', 'Eid al-Fitr',        'عيد الفطر المبارك',   3, true],
                ['2025-06-06', 'Eid al-Adha',        'عيد الأضحى المبارك',  4, true],
                ['2025-06-26', 'Islamic New Year',   'رأس السنة الهجرية',   1, true],
                ['2025-09-04', "Prophet's Birthday", 'المولد النبوي الشريف', 1, true],
            ],
            2026 => [
                ['2026-03-19', 'Eid al-Fitr',        'عيد الفطر المبارك',   3, true],
                ['2026-05-26', 'Eid al-Adha',        'عيد الأضحى المبارك',  4, true],
                ['2026-06-16', 'Islamic New Year',   'رأس السنة الهجرية',   1, true],
                ['2026-08-26', "Prophet's Birthday", 'المولد النبوي الشريف', 1, true],
            ],
            2027 => [
                ['2027-03-09', 'Eid al-Fitr',        'عيد الفطر المبارك',   3, true],
                ['2027-05-16', 'Eid al-Adha',        'عيد الأضحى المبارك',  4, true],
                ['2027-06-05', 'Islamic New Year',   'رأس السنة الهجرية',   1, true],
                ['2027-08-15', "Prophet's Birthday", 'المولد النبوي الشريف', 1, true],
            ],
        ];

        return array_map(
            fn (array $h) => new PublicHoliday(
                date: $h[0],
                name: $h[1],
                nameAr: $h[2],
                type: 'religious_islamic',
                daysOff: $h[3],
                islamicBased: $h[4],
            ),
            $data[$year],
        );
    }
}
