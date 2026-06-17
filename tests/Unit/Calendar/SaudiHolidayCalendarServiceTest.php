<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar;

use App\Services\Calendar\SaudiHolidayCalendarService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SaudiHolidayCalendarServiceTest extends TestCase
{
    private SaudiHolidayCalendarService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SaudiHolidayCalendarService();
    }

    /** @test */
    public function returns_calendar_for_2026(): void
    {
        $cal = $this->service->forYear(2026);
        $this->assertSame('SA', $cal->country);
        $this->assertSame(2026, $cal->year);
    }

    /** @test */
    public function calendar_contains_six_holidays(): void
    {
        // 2 fixed + 4 Islamic = 6
        $this->assertCount(6, $this->service->forYear(2026)->holidays);
    }

    /** @test */
    public function founding_day_is_february_22(): void
    {
        $this->assertTrue($this->service->forYear(2026)->isHoliday('2026-02-22'));
    }

    /** @test */
    public function national_day_is_september_23(): void
    {
        $this->assertTrue($this->service->forYear(2026)->isHoliday('2026-09-23'));
    }

    /** @test */
    public function eid_al_adha_2026_spans_four_days(): void
    {
        $cal = $this->service->forYear(2026);
        $h   = $cal->holidayOn('2026-05-26');
        $this->assertNotNull($h);
        $this->assertSame(4, $h->daysOff);
    }

    /** @test */
    public function unsupported_year_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->forYear(2028);
    }

    /** @test */
    public function saudi_weekend_days_are_friday_and_saturday(): void
    {
        $this->assertSame([5, 6], $this->service->forYear(2026)->weekendDays());
    }

    /** @test */
    public function ordinary_working_day_is_not_a_holiday(): void
    {
        $this->assertFalse($this->service->forYear(2026)->isHoliday('2026-03-15'));
    }
}
