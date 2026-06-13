<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable value object representing a date in the Islamic (Hijri) calendar.
 */
final readonly class HijriDate
{
    private const MONTH_NAMES_AR = [
        1  => 'محرم',
        2  => 'صفر',
        3  => 'ربيع الأول',
        4  => 'ربيع الآخر',
        5  => 'جمادى الأولى',
        6  => 'جمادى الآخرة',
        7  => 'رجب',
        8  => 'شعبان',
        9  => 'رمضان',
        10 => 'شوال',
        11 => 'ذو القعدة',
        12 => 'ذو الحجة',
    ];

    private const MONTH_NAMES_EN = [
        1  => 'Muharram',
        2  => 'Safar',
        3  => "Rabi' al-Awwal",
        4  => "Rabi' al-Akhir",
        5  => 'Jumada al-Ula',
        6  => 'Jumada al-Akhira',
        7  => 'Rajab',
        8  => "Sha'ban",
        9  => 'Ramadan',
        10 => 'Shawwal',
        11 => "Dhu al-Qi'dah",
        12 => 'Dhu al-Hijjah',
    ];

    public function __construct(
        public readonly int $year,
        public readonly int $month,
        public readonly int $day,
    ) {
        if ($year < 1 || $year > 9999) {
            throw new InvalidArgumentException("Invalid Hijri year: {$year}");
        }
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Invalid Hijri month: {$month}");
        }
        if ($day < 1 || $day > 30) {
            throw new InvalidArgumentException("Invalid Hijri day: {$day}");
        }
    }

    /**
     * Parse a Hijri date from an ISO-style string "YYYY-MM-DD".
     */
    public static function parse(string $date): self
    {
        $parts = explode('-', $date);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException(
                "Hijri date must be formatted as YYYY-MM-DD, got: {$date}"
            );
        }

        return new self((int) $parts[0], (int) $parts[1], (int) $parts[2]);
    }

    /** "YYYY-MM-DD" string representation. */
    public function toString(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    /**
     * Human-readable formatted date.
     *
     * @param string $locale  "ar" → Arabic with Eastern numerals, "en" → English
     */
    public function format(string $locale = 'en'): string
    {
        if ($locale === 'ar') {
            return sprintf(
                '%s %s %s',
                $this->toEasternArabic($this->day),
                self::MONTH_NAMES_AR[$this->month],
                $this->toEasternArabic($this->year)
            );
        }

        return sprintf('%d %s %d', $this->day, self::MONTH_NAMES_EN[$this->month], $this->year);
    }

    public function monthName(string $locale = 'en'): string
    {
        return $locale === 'ar' ? self::MONTH_NAMES_AR[$this->month] : self::MONTH_NAMES_EN[$this->month];
    }

    public function toArray(): array
    {
        return ['year' => $this->year, 'month' => $this->month, 'day' => $this->day];
    }

    private function toEasternArabic(int $value): string
    {
        return strtr((string) $value, '0123456789', '٠١٢٣٤٥٦٧٨٩');
    }
}
