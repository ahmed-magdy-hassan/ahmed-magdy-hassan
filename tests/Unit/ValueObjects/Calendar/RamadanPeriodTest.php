<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects\Calendar;

use App\ValueObjects\Calendar\RamadanPeriod;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RamadanPeriodTest extends TestCase
{
    private function make2026(): RamadanPeriod
    {
        return new RamadanPeriod(2026, 'EG', '2026-02-18', '2026-03-18');
    }

    /** @test */
    public function includes_returns_true_for_first_day(): void
    {
        $this->assertTrue($this->make2026()->includes('2026-02-18'));
    }

    /** @test */
    public function includes_returns_true_for_last_day(): void
    {
        $this->assertTrue($this->make2026()->includes('2026-03-18'));
    }

    /** @test */
    public function includes_returns_false_for_day_before_start(): void
    {
        $this->assertFalse($this->make2026()->includes('2026-02-17'));
    }

    /** @test */
    public function includes_returns_false_for_day_after_end(): void
    {
        $this->assertFalse($this->make2026()->includes('2026-03-19'));
    }

    /** @test */
    public function daily_hours_during_ramadan_is_6(): void
    {
        $this->assertSame(6, $this->make2026()->dailyHours('2026-03-01'));
    }

    /** @test */
    public function daily_hours_outside_ramadan_is_8(): void
    {
        $this->assertSame(8, $this->make2026()->dailyHours('2026-04-01'));
    }

    /** @test */
    public function duration_days_is_29_for_2026(): void
    {
        $this->assertSame(29, $this->make2026()->durationDays());
    }

    /** @test */
    public function duration_days_is_29_for_2025(): void
    {
        $p = new RamadanPeriod(2025, 'EG', '2025-03-01', '2025-03-29');
        $this->assertSame(29, $p->durationDays());
    }

    /** @test */
    public function invalid_reduced_hours_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RamadanPeriod(2026, 'EG', '2026-02-18', '2026-03-18', 0);
    }

    /** @test */
    public function to_array_has_all_keys(): void
    {
        $arr = $this->make2026()->toArray();
        foreach ([
            'year', 'country', 'start_date', 'end_date',
            'duration_days', 'reduced_daily_hours', 'standard_daily_hours', 'note',
        ] as $key) {
            $this->assertArrayHasKey($key, $arr);
        }
    }

    /** @test */
    public function to_array_standard_hours_is_8(): void
    {
        $this->assertSame(8, $this->make2026()->toArray()['standard_daily_hours']);
    }
}
