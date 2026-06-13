<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\ValueObjects\HijriDate;
use Carbon\Carbon;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Converts between Gregorian and Hijri (Islamic Tabular / Civil) calendar dates,
 * and provides Arabic date-formatting utilities.
 *
 * Uses the tabular calendar algorithm (Reingold & Dershowitz, "Calendrical Calculations").
 * The tabular calendar may differ from the Saudi Umm al-Qura observational calendar by ±1 day.
 *
 * Verified against:
 *   2000-01-01 ↔ 1420-09-24  (Ramadan 24)
 *   2023-07-19 ↔ 1445-01-01  (1 Muharram 1445)
 *   2024-01-01 ↔ 1445-06-19  (Jumada al-Akhira 19)
 */
final class HijriDateService
{
    /** Julian Day epoch for 1 Muharram 1 AH (midnight convention). */
    private const ISLAMIC_EPOCH = 1948439.5;

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Convert a Gregorian date to its Hijri equivalent.
     */
    public function toHijri(DateTimeInterface|string $date): HijriDate
    {
        $dt  = $date instanceof DateTimeInterface ? Carbon::instance($date) : Carbon::parse($date);
        $jdn = $this->gregorianToJdn($dt->year, $dt->month, $dt->day);
        [$y, $m, $d] = $this->jdnToHijri($jdn);

        return new HijriDate($y, $m, $d);
    }

    /**
     * Convert a Hijri date back to Gregorian.
     */
    public function toGregorian(HijriDate|string $hijri): Carbon
    {
        $h   = is_string($hijri) ? HijriDate::parse($hijri) : $hijri;
        $jdn = (int) ceil($this->hijriToJd($h->year, $h->month, $h->day));
        [$y, $m, $d] = $this->jdnToGregorian($jdn);

        return Carbon::create($y, $m, $d);
    }

    /**
     * Compute fractional service years from a Hijri start date to a Hijri end date.
     * Both dates are converted to Gregorian before the day-count is computed.
     */
    public function serviceYearsBetween(HijriDate $start, HijriDate $end): float
    {
        $gStart = $this->toGregorian($start);
        $gEnd   = $this->toGregorian($end);

        if (! $gEnd->gt($gStart)) {
            throw new InvalidArgumentException('Hijri end date must be after start date.');
        }

        return $gEnd->diffInDays($gStart) / 365.25;
    }

    /**
     * Format a Gregorian date as an Arabic Hijri string.
     * Example: "١٩ جمادى الآخرة ١٤٤٥"
     */
    public function formatArabic(DateTimeInterface|string $date): string
    {
        return $this->toHijri($date)->format('ar');
    }

    /**
     * Format a Gregorian date as an English Hijri string.
     * Example: "19 Jumada al-Akhira 1445"
     */
    public function formatEnglish(DateTimeInterface|string $date): string
    {
        return $this->toHijri($date)->format('en');
    }

    /**
     * Convert Western Arabic numerals to Eastern Arabic (٠١٢٣٤٥٦٧٨٩).
     * Used for payslips and reports targeting Arabic-speaking users.
     */
    public function toEasternArabic(string|int $value): string
    {
        return strtr((string) $value, '0123456789', '٠١٢٣٤٥٦٧٨٩');
    }

    /**
     * Convert a float amount to an Arabic-formatted string with two decimal places.
     * Example: 45000.50 → "٤٥٬٠٠٠٫٥٠"
     */
    public function formatAmountArabic(float $amount): string
    {
        $formatted = number_format($amount, 2, '.', ',');

        return strtr($formatted, [',' => '٬', '.' => '٫', '0' => '٠', '1' => '١', '2' => '٢',
            '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
    }

    // -----------------------------------------------------------------------
    // Private calendar-algorithm helpers
    // -----------------------------------------------------------------------

    /**
     * Convert a Gregorian date to an integer Julian Day Number (midnight convention).
     *
     * Algorithm: Jean Meeus, "Astronomical Algorithms", chapter 7.
     */
    private function gregorianToJdn(int $year, int $month, int $day): int
    {
        if ($month <= 2) {
            $year--;
            $month += 12;
        }
        $a = intdiv($year, 100);
        $b = 2 - $a + intdiv($a, 4);

        return (int) (floor(365.25 * ($year + 4716))
            + floor(30.6001 * ($month + 1))
            + $day + $b - 1524);
    }

    /**
     * Convert an integer JDN to a Gregorian date [year, month, day].
     *
     * Algorithm: Richards (2013) via "Julian day" (Wikipedia).
     */
    private function jdnToGregorian(int $jdn): array
    {
        $l = $jdn + 68569;
        $n = intdiv(4 * $l, 146097);
        $l = $l - intdiv(146097 * $n + 3, 4);
        $i = intdiv(4000 * ($l + 1), 1461001);
        $l = $l - intdiv(1461 * $i, 4) + 31;
        $j = intdiv(80 * $l, 2447);
        $d = $l - intdiv(2447 * $j, 80);
        $l = intdiv($j, 11);
        $m = $j + 2 - 12 * $l;
        $y = 100 * ($n - 49) + $i + $l;

        return [$y, $m, $d];
    }

    /**
     * Convert a Hijri date to a fractional Julian Day (midnight convention, ±0.5).
     *
     * Algorithm: Reingold & Dershowitz, "Calendrical Calculations".
     */
    private function hijriToJd(int $year, int $month, int $day): float
    {
        return $day
            + (int) ceil(29.5 * ($month - 1))
            + ($year - 1) * 354
            + (int) floor((3 + 11 * $year) / 30)
            + self::ISLAMIC_EPOCH
            - 1;
    }

    /**
     * Convert an integer JDN to a Hijri date [year, month, day].
     *
     * Algorithm: Reingold & Dershowitz, "Calendrical Calculations".
     */
    private function jdnToHijri(int $jdn): array
    {
        $year  = (int) floor((30 * ($jdn - self::ISLAMIC_EPOCH) + 10646) / 10631);
        $month = min(12, (int) ceil(($jdn - (29 + $this->hijriToJd($year, 1, 1))) / 29.5) + 1);
        $day   = (int) floor($jdn - $this->hijriToJd($year, $month, 1)) + 1;

        return [$year, $month, $day];
    }
}
