<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar;

use App\Services\Calendar\EgyptHolidayCalendarService;
use App\Services\Calendar\RamadanCalendarService;
use App\Services\Calendar\WorkingDayEvaluatorService;
use App\ValueObjects\Calendar\PublicHolidayCalendar;
use PHPUnit\Framework\TestCase;

final class WorkingDayEvaluatorServiceTest extends TestCase
{
    private WorkingDayEvaluatorService $evaluator;
    private PublicHolidayCalendar      $calendar2026;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator    = new WorkingDayEvaluatorService();
        $this->calendar2026 = (new EgyptHolidayCalendarService())->forYear(2026);
    }

    // ── Working day ─────────────────────────────────────────────────────────

    /** @test */
    public function regular_working_day_is_identified_correctly(): void
    {
        // 2026-04-01 is a Wednesday — not a holiday
        $r = $this->evaluator->evaluate('2026-04-01', $this->calendar2026);
        $this->assertTrue($r->isWorkingDay);
        $this->assertSame('working_day', $r->reason);
        $this->assertSame(8, $r->standardHours);
    }

    // ── Weekend ─────────────────────────────────────────────────────────────

    /** @test */
    public function friday_is_not_a_working_day(): void
    {
        // 2026-04-03 = Friday
        $r = $this->evaluator->evaluate('2026-04-03', $this->calendar2026);
        $this->assertFalse($r->isWorkingDay);
        $this->assertSame('weekend', $r->reason);
        $this->assertSame(0, $r->standardHours);
    }

    /** @test */
    public function saturday_is_not_a_working_day(): void
    {
        // 2026-04-04 = Saturday
        $r = $this->evaluator->evaluate('2026-04-04', $this->calendar2026);
        $this->assertFalse($r->isWorkingDay);
        $this->assertSame('weekend', $r->reason);
    }

    // ── Public holiday ──────────────────────────────────────────────────────

    /** @test */
    public function public_holiday_is_not_a_working_day(): void
    {
        // Eid al-Fitr 2026-03-19
        $r = $this->evaluator->evaluate('2026-03-19', $this->calendar2026);
        $this->assertFalse($r->isWorkingDay);
        $this->assertSame('public_holiday', $r->reason);
        $this->assertSame(0, $r->standardHours);
    }

    /** @test */
    public function public_holiday_result_includes_name(): void
    {
        $r = $this->evaluator->evaluate('2026-01-07', $this->calendar2026);
        $this->assertSame('Coptic Christmas', $r->holidayName);
        $this->assertNotNull($r->holidayNameAr);
    }

    /** @test */
    public function ordinary_working_day_has_null_holiday_name(): void
    {
        $r = $this->evaluator->evaluate('2026-04-01', $this->calendar2026);
        $this->assertNull($r->holidayName);
    }

    // ── Ramadan hours ───────────────────────────────────────────────────────

    /** @test */
    public function working_day_during_ramadan_has_6_hours(): void
    {
        $ramadan = (new RamadanCalendarService())->forYear(2026, 'EG');
        // 2026-03-01 is a Sunday — working day, Ramadan is 2026-02-18 to 03-18
        $r = $this->evaluator->evaluate('2026-03-01', $this->calendar2026, $ramadan);
        $this->assertTrue($r->isWorkingDay);
        $this->assertSame(6, $r->standardHours);
        $this->assertSame('ramadan', $r->reason);
    }

    /** @test */
    public function working_day_outside_ramadan_without_ramadan_config_has_8_hours(): void
    {
        $r = $this->evaluator->evaluate('2026-04-01', $this->calendar2026, null);
        $this->assertSame(8, $r->standardHours);
    }

    /** @test */
    public function working_day_after_ramadan_reverts_to_8_hours(): void
    {
        $ramadan = (new RamadanCalendarService())->forYear(2026, 'EG');
        // 2026-03-19 is Eid al-Fitr (holiday), but test the day AFTER Ramadan ends
        // Ramadan ends 2026-03-18, so 2026-03-23 (Monday) should be normal
        $r = $this->evaluator->evaluate('2026-03-23', $this->calendar2026, $ramadan);
        $this->assertTrue($r->isWorkingDay);
        $this->assertSame(8, $r->standardHours);
        $this->assertSame('working_day', $r->reason);
    }

    // ── to_array ─────────────────────────────────────────────────────────────

    /** @test */
    public function to_array_has_required_keys(): void
    {
        $r   = $this->evaluator->evaluate('2026-04-01', $this->calendar2026);
        $arr = $r->toArray();
        foreach (['date', 'is_working_day', 'standard_hours', 'reason'] as $key) {
            $this->assertArrayHasKey($key, $arr);
        }
    }

    /** @test */
    public function to_array_for_holiday_includes_holiday_name(): void
    {
        $r   = $this->evaluator->evaluate('2026-01-07', $this->calendar2026);
        $arr = $r->toArray();
        $this->assertArrayHasKey('holiday_name', $arr);
        $this->assertArrayHasKey('holiday_name_ar', $arr);
    }

    /** @test */
    public function to_array_for_working_day_excludes_holiday_name(): void
    {
        $r   = $this->evaluator->evaluate('2026-04-01', $this->calendar2026);
        $arr = $r->toArray();
        $this->assertArrayNotHasKey('holiday_name', $arr);
    }
}
