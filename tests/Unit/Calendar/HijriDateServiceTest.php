<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar;

use App\Services\Calendar\HijriDateService;
use App\ValueObjects\HijriDate;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HijriDateServiceTest extends TestCase
{
    private HijriDateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HijriDateService();
    }

    // -----------------------------------------------------------------------
    // Gregorian → Hijri
    // -----------------------------------------------------------------------

    /** @test */
    public function converts_well_known_gregorian_date_to_hijri(): void
    {
        // 2000-01-01 = 1420-09-24 (Ramadan 24)
        $hijri = $this->service->toHijri('2000-01-01');

        $this->assertSame(1420, $hijri->year);
        $this->assertSame(9, $hijri->month);
        $this->assertSame(24, $hijri->day);
    }

    /** @test */
    public function converts_first_day_of_hijri_year_1445(): void
    {
        // 1 Muharram 1445 AH = 2023-07-19 (tabular calendar)
        $hijri = $this->service->toHijri('2023-07-19');

        $this->assertSame(1445, $hijri->year);
        $this->assertSame(1, $hijri->month);
        $this->assertSame(1, $hijri->day);
    }

    /** @test */
    public function converts_mid_year_gregorian_date(): void
    {
        // 2024-01-01 = 1445-06-19 (Jumada al-Akhira 19)
        $hijri = $this->service->toHijri('2024-01-01');

        $this->assertSame(1445, $hijri->year);
        $this->assertSame(6, $hijri->month);
        $this->assertSame(19, $hijri->day);
    }

    /** @test */
    public function accepts_datetime_interface_input(): void
    {
        $date  = Carbon::parse('2024-01-01');
        $hijri = $this->service->toHijri($date);

        $this->assertSame(1445, $hijri->year);
        $this->assertSame(6, $hijri->month);
        $this->assertSame(19, $hijri->day);
    }

    // -----------------------------------------------------------------------
    // Hijri → Gregorian
    // -----------------------------------------------------------------------

    /** @test */
    public function converts_hijri_to_gregorian_value_object(): void
    {
        $gregorian = $this->service->toGregorian(new HijriDate(1445, 6, 19));

        $this->assertSame(2024, $gregorian->year);
        $this->assertSame(1, $gregorian->month);
        $this->assertSame(1, $gregorian->day);
    }

    /** @test */
    public function converts_hijri_string_to_gregorian(): void
    {
        $gregorian = $this->service->toGregorian('1445-01-01');

        $this->assertSame(2023, $gregorian->year);
        $this->assertSame(7, $gregorian->month);
        $this->assertSame(19, $gregorian->day);
    }

    // -----------------------------------------------------------------------
    // Round-trip
    // -----------------------------------------------------------------------

    /** @test */
    public function round_trip_gregorian_hijri_gregorian_is_stable(): void
    {
        $dates = ['1990-03-15', '2005-06-30', '2023-12-31', '2024-01-01'];

        foreach ($dates as $original) {
            $hijri  = $this->service->toHijri($original);
            $back   = $this->service->toGregorian($hijri);

            $this->assertSame($original, $back->toDateString(), "Round-trip failed for {$original}");
        }
    }

    // -----------------------------------------------------------------------
    // Service years
    // -----------------------------------------------------------------------

    /** @test */
    public function service_years_between_hijri_dates_matches_gregorian_calculation(): void
    {
        // 1 Muharram 1441 → 1 Muharram 1446 = exactly 5 Hijri years
        // Gregorian: 2019-08-31 → 2024-07-07 = 1772 days ≈ 4.85 years (365.25 basis)
        $start = new HijriDate(1441, 1, 1);
        $end   = new HijriDate(1446, 1, 1);

        $years = $this->service->serviceYearsBetween($start, $end);

        // 5 Hijri years ≈ 4.85–4.90 Gregorian years (the Hijri year is ~354 days)
        $this->assertGreaterThan(4.8, $years);
        $this->assertLessThan(4.95, $years);
    }

    /** @test */
    public function service_years_throws_when_end_before_start(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->serviceYearsBetween(
            new HijriDate(1446, 1, 1),
            new HijriDate(1441, 1, 1),
        );
    }

    // -----------------------------------------------------------------------
    // Formatting
    // -----------------------------------------------------------------------

    /** @test */
    public function format_arabic_returns_eastern_numerals_and_arabic_month_name(): void
    {
        // 2024-01-01 = 19 Jumada al-Akhira 1445
        $formatted = $this->service->formatArabic('2024-01-01');

        $this->assertStringContainsString('جمادى الآخرة', $formatted);
        // Eastern-Arabic digit ١ (U+0661) appears in 19 → ١٩
        $this->assertStringContainsString('١٩', $formatted);
    }

    /** @test */
    public function format_english_returns_western_numerals_and_english_month_name(): void
    {
        $formatted = $this->service->formatEnglish('2024-01-01');

        $this->assertSame('19 Jumada al-Akhira 1445', $formatted);
    }

    /** @test */
    public function to_eastern_arabic_converts_digits_correctly(): void
    {
        $this->assertSame('٠١٢٣٤٥٦٧٨٩', $this->service->toEasternArabic('0123456789'));
        $this->assertSame('٤٥٠٠٠', $this->service->toEasternArabic(45000));
    }

    /** @test */
    public function format_amount_arabic_formats_with_arabic_separators(): void
    {
        $formatted = $this->service->formatAmountArabic(45000.50);

        // Should use Arabic thousands separator (٬) and decimal point (٫)
        $this->assertStringContainsString('٬', $formatted);
        $this->assertStringContainsString('٫', $formatted);
        $this->assertStringContainsString('٥٠', $formatted);
    }
}
