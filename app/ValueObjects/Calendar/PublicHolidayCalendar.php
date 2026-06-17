<?php

declare(strict_types=1);

namespace App\ValueObjects\Calendar;

use Carbon\CarbonImmutable;

/**
 * Full public-holiday calendar for one country and one Gregorian year.
 *
 * Provides holiday lookup, working-day counting, and serialisation for the
 * API layer.
 */
final readonly class PublicHolidayCalendar
{
    /** @param PublicHoliday[] $holidays */
    public function __construct(
        /** ISO 3166-1 alpha-2 country code ('EG' or 'SA'). */
        public readonly string $country,

        public readonly int $year,

        /** @var PublicHoliday[] */
        public readonly array $holidays,
    ) {}

    /** Returns true if the given Gregorian date is a public holiday. */
    public function isHoliday(string $date): bool
    {
        foreach ($this->holidays as $h) {
            if ($h->covers($date)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the holiday covering the given date, or null if it is not a
     * public holiday.
     */
    public function holidayOn(string $date): ?PublicHoliday
    {
        foreach ($this->holidays as $h) {
            if ($h->covers($date)) {
                return $h;
            }
        }

        return null;
    }

    /**
     * Counts working days (Mon–Fri for Egypt, Sun–Thu for Saudi) in the
     * given month that are NOT public holidays.
     */
    public function workingDaysInMonth(int $month): int
    {
        $workdays = $this->weekendDays();
        $count    = 0;

        $start = CarbonImmutable::create($this->year, $month, 1);
        $end   = $start->endOfMonth();

        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            if (!in_array($d->dayOfWeek, $workdays, true) && !$this->isHoliday($d->toDateString())) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Returns the ISO day-of-week numbers (0=Sun…6=Sat) that are the
     * weekend days for this country.
     *
     * Egypt: Friday + Saturday (5, 6)
     * Saudi: Friday + Saturday (5, 6)
     *
     * @return int[]
     */
    public function weekendDays(): array
    {
        return match ($this->country) {
            'SA'    => [5, 6],   // Fri + Sat
            default => [5, 6],   // Egypt: Fri + Sat as well
        };
    }

    public function toArray(): array
    {
        return [
            'country'        => $this->country,
            'year'           => $this->year,
            'holiday_count'  => count($this->holidays),
            'holidays'       => array_map(fn (PublicHoliday $h) => $h->toArray(), $this->holidays),
        ];
    }
}
