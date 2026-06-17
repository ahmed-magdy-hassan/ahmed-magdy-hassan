<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar;

use App\Services\Calendar\EgyptHolidayCalendarService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EgyptHolidayCalendarServiceTest extends TestCase
{
    private EgyptHolidayCalendarService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EgyptHolidayCalendarService();
    }

    /** @test */
    public function returns_calendar_for_2026(): void
    {
        $cal = $this->service->forYear(2026);
        $this->assertSame('EG', $cal->country);
        $this->assertSame(2026, $cal->year);
    }

    /** @test */
    public function calendar_contains_eleven_holidays(): void
    {
        // 7 fixed + 4 Islamic = 11
        $this->assertCount(11, $this->service->forYear(2026)->holidays);
    }

    /** @test */
    public function coptic_christmas_is_january_7(): void
    {
        $cal = $this->service->forYear(2026);
        $this->assertTrue($cal->isHoliday('2026-01-07'));
    }

    /** @test */
    public function sinai_liberation_day_is_april_25(): void
    {
        $this->assertTrue($this->service->forYear(2026)->isHoliday('2026-04-25'));
    }

    /** @test */
    public function labour_day_is_may_1(): void
    {
        $this->assertTrue($this->service->forYear(2026)->isHoliday('2026-05-01'));
    }

    /** @test */
    public function armed_forces_day_is_october_6(): void
    {
        $this->assertTrue($this->service->forYear(2026)->isHoliday('2026-10-06'));
    }

    /** @test */
    public function eid_al_fitr_2026_spans_march_19_to_21(): void
    {
        $cal = $this->service->forYear(2026);
        $this->assertTrue($cal->isHoliday('2026-03-19'));
        $this->assertTrue($cal->isHoliday('2026-03-20'));
        $this->assertTrue($cal->isHoliday('2026-03-21'));
        $this->assertFalse($cal->isHoliday('2026-03-22'));
    }

    /** @test */
    public function eid_al_adha_2026_spans_four_days(): void
    {
        $cal = $this->service->forYear(2026);
        $h = $cal->holidayOn('2026-05-26');
        $this->assertNotNull($h);
        $this->assertSame(4, $h->daysOff);
    }

    /** @test */
    public function ordinary_working_day_is_not_a_holiday(): void
    {
        $this->assertFalse($this->service->forYear(2026)->isHoliday('2026-04-01'));
    }

    /** @test */
    public function unsupported_year_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->forYear(2024);
    }

    /** @test */
    public function returns_calendar_for_2025_and_2027(): void
    {
        $this->assertSame('EG', $this->service->forYear(2025)->country);
        $this->assertSame('EG', $this->service->forYear(2027)->country);
    }

    /** @test */
    public function to_array_holiday_count_is_eleven(): void
    {
        $arr = $this->service->forYear(2026)->toArray();
        $this->assertSame(11, $arr['holiday_count']);
    }

    /** @test */
    public function islamic_based_flag_is_true_for_eid(): void
    {
        $cal = $this->service->forYear(2026);
        $h   = $cal->holidayOn('2026-03-19');
        $this->assertNotNull($h);
        $this->assertTrue($h->islamicBased);
    }

    /** @test */
    public function islamic_based_flag_is_false_for_national_holidays(): void
    {
        $cal = $this->service->forYear(2026);
        $h   = $cal->holidayOn('2026-07-23');
        $this->assertNotNull($h);
        $this->assertFalse($h->islamicBased);
    }
}
