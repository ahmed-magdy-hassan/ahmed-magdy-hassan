<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\HijriDate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HijriDateTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Constructor — valid construction
    // -----------------------------------------------------------------------

    /** @test */
    public function constructs_and_exposes_properties(): void
    {
        $h = new HijriDate(1445, 6, 19);

        $this->assertSame(1445, $h->year);
        $this->assertSame(6, $h->month);
        $this->assertSame(19, $h->day);
    }

    /** @test */
    public function boundary_year_1_is_valid(): void
    {
        $h = new HijriDate(1, 1, 1);
        $this->assertSame(1, $h->year);
    }

    /** @test */
    public function boundary_year_9999_is_valid(): void
    {
        $h = new HijriDate(9999, 12, 30);
        $this->assertSame(9999, $h->year);
    }

    // -----------------------------------------------------------------------
    // Constructor — validation guards
    // -----------------------------------------------------------------------

    /** @test */
    public function throws_on_year_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Hijri year: 0');
        new HijriDate(0, 1, 1);
    }

    /** @test */
    public function throws_on_year_above_9999(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Hijri year: 10000');
        new HijriDate(10000, 1, 1);
    }

    /** @test */
    public function throws_on_month_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Hijri month: 0');
        new HijriDate(1445, 0, 1);
    }

    /** @test */
    public function throws_on_month_thirteen(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Hijri month: 13');
        new HijriDate(1445, 13, 1);
    }

    /** @test */
    public function throws_on_day_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Hijri day: 0');
        new HijriDate(1445, 6, 0);
    }

    /** @test */
    public function throws_on_day_thirty_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Hijri day: 31');
        new HijriDate(1445, 6, 31);
    }

    // -----------------------------------------------------------------------
    // parse()
    // -----------------------------------------------------------------------

    /** @test */
    public function parse_creates_correct_instance(): void
    {
        $h = HijriDate::parse('1445-06-19');

        $this->assertSame(1445, $h->year);
        $this->assertSame(6, $h->month);
        $this->assertSame(19, $h->day);
    }

    /** @test */
    public function parse_throws_on_too_few_parts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Hijri date must be formatted as YYYY-MM-DD');
        HijriDate::parse('1445-06');
    }

    /** @test */
    public function parse_throws_on_wrong_separator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HijriDate::parse('1445/06/19');
    }

    /** @test */
    public function parse_throws_on_too_many_parts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HijriDate::parse('1445-06-19-00');
    }

    /** @test */
    public function parse_propagates_constructor_validation(): void
    {
        // 3 parts but invalid month value
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Hijri month: 13');
        HijriDate::parse('1445-13-01');
    }

    // -----------------------------------------------------------------------
    // toString()
    // -----------------------------------------------------------------------

    /** @test */
    public function to_string_zero_pads_month_and_day(): void
    {
        $this->assertSame('1445-06-01', (new HijriDate(1445, 6, 1))->toString());
    }

    /** @test */
    public function to_string_pads_year_to_four_digits(): void
    {
        $this->assertSame('0001-01-01', (new HijriDate(1, 1, 1))->toString());
    }

    // -----------------------------------------------------------------------
    // format()
    // -----------------------------------------------------------------------

    /** @test */
    public function format_defaults_to_english(): void
    {
        $formatted = (new HijriDate(1445, 6, 19))->format();
        $this->assertSame('19 Jumada al-Akhira 1445', $formatted);
    }

    /** @test */
    public function format_english_all_twelve_months_have_names(): void
    {
        for ($m = 1; $m <= 12; $m++) {
            $formatted = (new HijriDate(1445, $m, 1))->format('en');
            $this->assertStringContainsString((string) 1445, $formatted, "Month {$m} missing year");
            $this->assertNotEmpty($formatted);
        }
    }

    /** @test */
    public function format_arabic_uses_eastern_numerals(): void
    {
        $formatted = (new HijriDate(1445, 9, 1))->format('ar');

        // Eastern Arabic digit ١ (U+0661) should appear in "١٤٤٥" and "١"
        $this->assertStringContainsString('١', $formatted);
        // Should contain Arabic month name for Ramadan
        $this->assertStringContainsString('رمضان', $formatted);
    }

    // -----------------------------------------------------------------------
    // monthName()
    // -----------------------------------------------------------------------

    /** @test */
    public function month_name_english_returns_correct_names(): void
    {
        $this->assertSame('Muharram',        (new HijriDate(1445,  1, 1))->monthName('en'));
        $this->assertSame("Dhu al-Hijjah",   (new HijriDate(1445, 12, 1))->monthName('en'));
    }

    /** @test */
    public function month_name_arabic_returns_arabic_names(): void
    {
        $this->assertSame('محرم',     (new HijriDate(1445,  1, 1))->monthName('ar'));
        $this->assertSame('رمضان',    (new HijriDate(1445,  9, 1))->monthName('ar'));
        $this->assertSame('ذو الحجة', (new HijriDate(1445, 12, 1))->monthName('ar'));
    }

    /** @test */
    public function month_name_defaults_to_english(): void
    {
        $this->assertSame('Safar', (new HijriDate(1445, 2, 1))->monthName());
    }

    // -----------------------------------------------------------------------
    // toArray()
    // -----------------------------------------------------------------------

    /** @test */
    public function to_array_contains_year_month_day(): void
    {
        $arr = (new HijriDate(1445, 6, 19))->toArray();

        $this->assertSame(['year' => 1445, 'month' => 6, 'day' => 19], $arr);
    }
}
