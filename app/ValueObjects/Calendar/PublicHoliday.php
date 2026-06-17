<?php

declare(strict_types=1);

namespace App\ValueObjects\Calendar;

use Carbon\CarbonImmutable;

/**
 * A single public holiday entry in a country calendar.
 *
 * Islamic-based holidays (Eid, Hijri New Year, etc.) shift each year; the
 * service layer stores their Gregorian equivalents per year so callers don't
 * need to know the Hijri conversion.
 */
final readonly class PublicHoliday
{
    public function __construct(
        /** Gregorian date of the first day off. */
        public readonly string $date,

        /** English name. */
        public readonly string $name,

        /** Arabic name. */
        public readonly string $nameAr,

        /**
         * Holiday category.
         * Values: 'national' | 'religious_islamic' | 'religious_coptic' | 'international'
         */
        public readonly string $type,

        /** Number of consecutive calendar days off (≥1). */
        public readonly int $daysOff = 1,

        /**
         * True for Islamic-calendar events whose Gregorian date shifts
         * ~11 days earlier each year.
         */
        public readonly bool $islamicBased = false,
    ) {}

    public function startDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date);
    }

    public function endDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date)->addDays($this->daysOff - 1);
    }

    /** Returns true if $date falls within this holiday's range. */
    public function covers(string $date): bool
    {
        $d = CarbonImmutable::parse($date);

        return $d->gte($this->startDate()) && $d->lte($this->endDate());
    }

    public function toArray(): array
    {
        return [
            'date'          => $this->date,
            'end_date'      => $this->endDate()->toDateString(),
            'name'          => $this->name,
            'name_ar'       => $this->nameAr,
            'type'          => $this->type,
            'days_off'      => $this->daysOff,
            'islamic_based' => $this->islamicBased,
        ];
    }
}
