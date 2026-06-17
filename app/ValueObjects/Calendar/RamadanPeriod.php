<?php

declare(strict_types=1);

namespace App\ValueObjects\Calendar;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Ramadan period for a specific Gregorian year and country.
 *
 * Egyptian Labour Law 14/2025 mandates reduced working hours during Ramadan
 * (6 hours/day for most sectors).  Saudi labour law specifies a 6-hour
 * working day for Muslim employees during Ramadan.
 *
 * Dates are astronomical estimates; the precise start depends on moon-sighting
 * declarations in each country.  Services storing these values should allow
 * per-company override.
 */
final readonly class RamadanPeriod
{
    /** Standard Egyptian/Saudi working hours outside Ramadan. */
    public const int STANDARD_DAILY_HOURS = 8;

    /** Reduced daily working hours mandated by law during Ramadan. */
    public const int RAMADAN_DAILY_HOURS = 6;

    public function __construct(
        public readonly int    $year,

        /** ISO 3166-1 alpha-2 ('EG' or 'SA'). */
        public readonly string $country,

        /** Gregorian start date (first day of Ramadan). */
        public readonly string $startDate,

        /** Gregorian end date (last day of Ramadan / eve of Eid). */
        public readonly string $endDate,

        /** Standard reduced daily working hours (default 6). */
        public readonly int $reducedDailyHours = self::RAMADAN_DAILY_HOURS,

        /** Notes on the astronomical calculation used. */
        public readonly string $note = 'Astronomical estimate; confirm with official moon-sighting declaration.',
    ) {
        if ($reducedDailyHours < 1 || $reducedDailyHours > 24) {
            throw new InvalidArgumentException('reducedDailyHours must be 1–24.');
        }
    }

    public function start(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->startDate);
    }

    public function end(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->endDate);
    }

    public function durationDays(): int
    {
        return (int) $this->start()->diffInDays($this->end()) + 1;
    }

    /** Returns true if the given Gregorian date falls within Ramadan. */
    public function includes(string $date): bool
    {
        $d = CarbonImmutable::parse($date);

        return $d->gte($this->start()) && $d->lte($this->end());
    }

    /** Standard hours applicable on the given date (Ramadan-reduced or normal). */
    public function dailyHours(string $date): int
    {
        return $this->includes($date) ? $this->reducedDailyHours : self::STANDARD_DAILY_HOURS;
    }

    public function toArray(): array
    {
        return [
            'year'                  => $this->year,
            'country'               => $this->country,
            'start_date'            => $this->startDate,
            'end_date'              => $this->endDate,
            'duration_days'         => $this->durationDays(),
            'reduced_daily_hours'   => $this->reducedDailyHours,
            'standard_daily_hours'  => self::STANDARD_DAILY_HOURS,
            'note'                  => $this->note,
        ];
    }
}
