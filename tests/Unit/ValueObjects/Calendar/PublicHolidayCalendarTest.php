<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects\Calendar;

use App\ValueObjects\Calendar\PublicHoliday;
use App\ValueObjects\Calendar\PublicHolidayCalendar;
use PHPUnit\Framework\TestCase;

final class PublicHolidayCalendarTest extends TestCase
{
    private function makeCalendar(): PublicHolidayCalendar
    {
        return new PublicHolidayCalendar('EG', 2026, [
            new PublicHoliday('2026-01-07', 'Coptic Christmas', 'عيد الميلاد', 'religious_coptic', 1),
            new PublicHoliday('2026-03-19', 'Eid al-Fitr',      'عيد الفطر',   'religious_islamic', 3, true),
        ]);
    }

    /** @test */
    public function is_holiday_returns_true_for_first_day_of_holiday(): void
    {
        $this->assertTrue($this->makeCalendar()->isHoliday('2026-01-07'));
    }

    /** @test */
    public function is_holiday_returns_true_within_multi_day_range(): void
    {
        $cal = $this->makeCalendar();
        $this->assertTrue($cal->isHoliday('2026-03-19'));
        $this->assertTrue($cal->isHoliday('2026-03-20'));
        $this->assertTrue($cal->isHoliday('2026-03-21'));
    }

    /** @test */
    public function is_holiday_returns_false_the_day_after_multi_day_holiday(): void
    {
        $this->assertFalse($this->makeCalendar()->isHoliday('2026-03-22'));
    }

    /** @test */
    public function is_holiday_returns_false_for_ordinary_day(): void
    {
        $this->assertFalse($this->makeCalendar()->isHoliday('2026-04-01'));
    }

    /** @test */
    public function holiday_on_returns_null_for_non_holiday(): void
    {
        $this->assertNull($this->makeCalendar()->holidayOn('2026-04-01'));
    }

    /** @test */
    public function holiday_on_returns_correct_holiday(): void
    {
        $h = $this->makeCalendar()->holidayOn('2026-03-20');
        $this->assertNotNull($h);
        $this->assertSame('Eid al-Fitr', $h->name);
    }

    /** @test */
    public function egypt_weekend_days_are_friday_and_saturday(): void
    {
        $this->assertSame([5, 6], $this->makeCalendar()->weekendDays());
    }

    /** @test */
    public function to_array_has_required_keys(): void
    {
        $arr = $this->makeCalendar()->toArray();
        foreach (['country', 'year', 'holiday_count', 'holidays'] as $key) {
            $this->assertArrayHasKey($key, $arr);
        }
    }

    /** @test */
    public function to_array_holiday_count_matches_holidays_array(): void
    {
        $arr = $this->makeCalendar()->toArray();
        $this->assertSame(2, $arr['holiday_count']);
        $this->assertCount(2, $arr['holidays']);
    }

    /** @test */
    public function public_holiday_covers_only_its_own_dates(): void
    {
        $h = new PublicHoliday('2026-03-19', 'Eid al-Fitr', 'عيد الفطر', 'religious_islamic', 3, true);
        $this->assertTrue($h->covers('2026-03-19'));
        $this->assertTrue($h->covers('2026-03-21'));
        $this->assertFalse($h->covers('2026-03-22'));
        $this->assertFalse($h->covers('2026-03-18'));
    }

    /** @test */
    public function public_holiday_to_array_has_all_keys(): void
    {
        $h = new PublicHoliday('2026-01-07', 'Coptic Christmas', 'عيد الميلاد', 'religious_coptic', 1);
        $arr = $h->toArray();
        foreach (['date', 'end_date', 'name', 'name_ar', 'type', 'days_off', 'islamic_based'] as $key) {
            $this->assertArrayHasKey($key, $arr);
        }
    }
}
