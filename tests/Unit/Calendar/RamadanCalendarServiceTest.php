<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar;

use App\Services\Calendar\RamadanCalendarService;
use App\ValueObjects\Calendar\RamadanPeriod;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RamadanCalendarServiceTest extends TestCase
{
    private RamadanCalendarService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RamadanCalendarService();
    }

    /** @test */
    public function ramadan_2025_starts_march_1(): void
    {
        $p = $this->service->forYear(2025, 'EG');
        $this->assertSame('2025-03-01', $p->startDate);
    }

    /** @test */
    public function ramadan_2025_ends_march_29(): void
    {
        $this->assertSame('2025-03-29', $this->service->forYear(2025, 'EG')->endDate);
    }

    /** @test */
    public function ramadan_2026_starts_february_18(): void
    {
        $this->assertSame('2026-02-18', $this->service->forYear(2026, 'EG')->startDate);
    }

    /** @test */
    public function ramadan_2026_ends_march_18(): void
    {
        $this->assertSame('2026-03-18', $this->service->forYear(2026, 'EG')->endDate);
    }

    /** @test */
    public function ramadan_2027_starts_february_7(): void
    {
        $this->assertSame('2027-02-07', $this->service->forYear(2027, 'EG')->startDate);
    }

    /** @test */
    public function reduced_hours_is_6_for_both_countries(): void
    {
        $this->assertSame(6, $this->service->forYear(2026, 'EG')->reducedDailyHours);
        $this->assertSame(6, $this->service->forYear(2026, 'SA')->reducedDailyHours);
    }

    /** @test */
    public function is_ramadan_returns_true_during_ramadan(): void
    {
        $this->assertTrue($this->service->isRamadan('2026-03-01', 2026, 'EG'));
    }

    /** @test */
    public function is_ramadan_returns_false_outside_ramadan(): void
    {
        $this->assertFalse($this->service->isRamadan('2026-04-01', 2026, 'EG'));
    }

    /** @test */
    public function same_dates_for_eg_and_sa(): void
    {
        $eg = $this->service->forYear(2026, 'EG');
        $sa = $this->service->forYear(2026, 'SA');
        $this->assertSame($eg->startDate, $sa->startDate);
        $this->assertSame($eg->endDate, $sa->endDate);
    }

    /** @test */
    public function unsupported_year_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->forYear(2024, 'EG');
    }

    /** @test */
    public function unsupported_country_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->forYear(2026, 'AE');
    }

    /** @test */
    public function country_code_is_case_insensitive(): void
    {
        $p = $this->service->forYear(2026, 'eg');
        $this->assertSame('EG', $p->country);
    }

    /** @test */
    public function ramadan_shifts_earlier_each_year(): void
    {
        $r25 = $this->service->forYear(2025);
        $r26 = $this->service->forYear(2026);
        $this->assertLessThan(
            $r25->start()->getTimestamp(),
            $r26->start()->getTimestamp(),
        );
    }
}
