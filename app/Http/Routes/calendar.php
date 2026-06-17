<?php

/**
 * Public-holiday calendar & working-day evaluation routes.
 *
 * Include in routes/api.php:
 *   require __DIR__ . '/../app/Http/Routes/calendar.php';
 *
 * ============================================================
 * ENDPOINT REFERENCE
 * ============================================================
 *
 * GET /api/hr/calendar/{country}/holidays?year=YYYY
 *   Full public-holiday list for the country and year.
 *   country: 'eg' | 'sa'   year: 2025–2027
 *   200: PublicHolidayCalendar
 *
 * GET /api/hr/calendar/{country}/ramadan?year=YYYY
 *   Ramadan period with reduced daily hours.
 *   200: RamadanPeriod
 *
 * GET /api/hr/calendar/{country}/working-day?date=YYYY-MM-DD
 *   Evaluates a single date: is it a working day? How many hours?
 *   200: WorkingDayResult
 * ============================================================
 */

use App\Http\Controllers\Hr\Calendar\HolidayCalendarController;
use Illuminate\Support\Facades\Route;

Route::prefix('hr/calendar/{country}')->middleware(['auth:sanctum'])->group(function (): void {
    Route::get('holidays',    [HolidayCalendarController::class, 'holidays']);
    Route::get('ramadan',     [HolidayCalendarController::class, 'ramadan']);
    Route::get('working-day', [HolidayCalendarController::class, 'workingDay']);
});
