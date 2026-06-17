<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Calendar;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature (HTTP) tests for HolidayCalendarController.
 *
 * Endpoints:
 *   GET /api/hr/calendar/{country}/holidays?year=YYYY
 *   GET /api/hr/calendar/{country}/ramadan?year=YYYY
 *   GET /api/hr/calendar/{country}/working-day?date=YYYY-MM-DD
 */
final class HolidayCalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    private Employee $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = Employee::factory()->create();
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /api/hr/calendar/{country}/holidays
    // ──────────────────────────────────────────────────────────────────────

    /** @test */
    public function holidays_returns_200_with_egypt_calendar(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/hr/calendar/eg/holidays?year=2026')
            ->assertStatus(200)
            ->assertJsonStructure(['country', 'year', 'holiday_count', 'holidays'])
            ->assertJsonPath('country', 'EG')
            ->assertJsonPath('year', 2026);
    }

    /** @test */
    public function holidays_returns_200_with_saudi_calendar(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/hr/calendar/sa/holidays?year=2026')
            ->assertStatus(200)
            ->assertJsonPath('country', 'SA');
    }

    /** @test */
    public function holidays_egypt_has_11_entries_for_2026(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/hr/calendar/eg/holidays?year=2026')
            ->assertJsonPath('holiday_count', 11);
    }

    /** @test */
    public function holidays_returns_422_for_unsupported_year(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/hr/calendar/eg/holidays?year=2024')
            ->assertStatus(422);
    }

    /** @test */
    public function holidays_returns_422_when_year_missing(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/hr/calendar/eg/holidays')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('year');
    }

    /** @test */
    public function holidays_returns_422_for_unknown_country(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/hr/calendar/ae/holidays?year=2026')
            ->assertStatus(422);
    }

    /** @test */
    public function holidays_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/hr/calendar/eg/holidays?year=2026')
            ->assertStatus(401);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /api/hr/calendar/{country}/ramadan
    // ──────────────────────────────────────────────────────────────────────

    /** @test */
    public function ramadan_returns_200_with_correct_structure(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/hr/calendar/eg/ramadan?year=2026')
            ->assertStatus(200)
            ->assertJsonStructure([
                'year', 'country', 'start_date', 'end_date',
                'duration_days', 'reduced_daily_hours', 'standard_daily_hours', 'note',
            ]);
    }

    /** @test */
    public function ramadan_2026_starts_february_18(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/hr/calendar/eg/ramadan?year=2026')
            ->assertJsonPath('start_date', '2026-02-18');
    }

    /** @test */
    public function ramadan_reduced_hours_is_6(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/hr/calendar/eg/ramadan?year=2026')
            ->assertJsonPath('reduced_daily_hours', 6);
    }

    /** @test */
    public function ramadan_returns_422_when_year_missing(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/hr/calendar/eg/ramadan')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('year');
    }

    /** @test */
    public function ramadan_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/hr/calendar/eg/ramadan?year=2026')
            ->assertStatus(401);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /api/hr/calendar/{country}/working-day
    // ──────────────────────────────────────────────────────────────────────

    /** @test */
    public function working_day_returns_200_for_ordinary_weekday(): void
    {
        // 2026-04-01 = Wednesday (not a holiday)
        $this->actingAs($this->user)
            ->getJson('/api/hr/calendar/eg/working-day?date=2026-04-01')
            ->assertStatus(200)
            ->assertJsonPath('is_working_day', true)
            ->assertJsonPath('standard_hours', 8)
            ->assertJsonPath('reason', 'working_day');
    }

    /** @test */
    public function working_day_returns_false_for_public_holiday(): void
    {
        // Eid al-Fitr
        $this->actingAs($this->user)
            ->getJson('/api/hr/calendar/eg/working-day?date=2026-03-19')
            ->assertStatus(200)
            ->assertJsonPath('is_working_day', false)
            ->assertJsonPath('reason', 'public_holiday');
    }

    /** @test */
    public function working_day_returns_false_for_friday(): void
    {
        // 2026-04-03 = Friday
        $this->actingAs($this->user)
            ->getJson('/api/hr/calendar/eg/working-day?date=2026-04-03')
            ->assertJsonPath('is_working_day', false)
            ->assertJsonPath('reason', 'weekend');
    }

    /** @test */
    public function working_day_during_ramadan_has_6_hours(): void
    {
        // 2026-03-04 = Wednesday, inside Ramadan 2026
        $this->actingAs($this->user)
            ->getJson('/api/hr/calendar/eg/working-day?date=2026-03-04')
            ->assertJsonPath('is_working_day', true)
            ->assertJsonPath('standard_hours', 6)
            ->assertJsonPath('reason', 'ramadan');
    }

    /** @test */
    public function working_day_returns_422_when_date_missing(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/hr/calendar/eg/working-day')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('date');
    }

    /** @test */
    public function working_day_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/hr/calendar/eg/working-day?date=2026-04-01')
            ->assertStatus(401);
    }
}
