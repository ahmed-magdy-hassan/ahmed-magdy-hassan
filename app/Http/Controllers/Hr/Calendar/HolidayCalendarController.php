<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr\Calendar;

use App\Http\Requests\Hr\Calendar\HolidayCalendarRequest;
use App\Http\Requests\Hr\Calendar\WorkingDayRequest;
use App\Services\Calendar\EgyptHolidayCalendarService;
use App\Services\Calendar\RamadanCalendarService;
use App\Services\Calendar\SaudiHolidayCalendarService;
use App\Services\Calendar\WorkingDayEvaluatorService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

/**
 * Exposes public-holiday calendars and working-day evaluation.
 *
 * Routes:
 *   GET /api/hr/calendar/{country}/holidays?year=YYYY
 *   GET /api/hr/calendar/{country}/ramadan?year=YYYY
 *   GET /api/hr/calendar/{country}/working-day?date=YYYY-MM-DD
 */
final class HolidayCalendarController extends Controller
{
    public function __construct(
        private readonly EgyptHolidayCalendarService  $egyptCalendar,
        private readonly SaudiHolidayCalendarService  $saudiCalendar,
        private readonly RamadanCalendarService        $ramadanService,
        private readonly WorkingDayEvaluatorService    $evaluator,
    ) {}

    /**
     * GET /api/hr/calendar/{country}/holidays?year=YYYY
     *
     * Returns the full public-holiday list for the country and year.
     * Country must be 'eg' or 'sa' (case-insensitive).
     */
    public function holidays(HolidayCalendarRequest $request, string $country): JsonResponse
    {
        $year = (int) $request->input('year');

        try {
            $calendar = match (strtolower($country)) {
                'eg'    => $this->egyptCalendar->forYear($year),
                'sa'    => $this->saudiCalendar->forYear($year),
                default => null,
            };
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($calendar === null) {
            return response()->json(['message' => "Unsupported country '{$country}'. Use 'eg' or 'sa'."], 422);
        }

        return response()->json($calendar->toArray());
    }

    /**
     * GET /api/hr/calendar/{country}/ramadan?year=YYYY
     *
     * Returns the Ramadan period (start, end, reduced daily hours) for the
     * given year and country.
     */
    public function ramadan(HolidayCalendarRequest $request, string $country): JsonResponse
    {
        $year = (int) $request->input('year');

        try {
            $period = $this->ramadanService->forYear($year, strtoupper($country));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($period->toArray());
    }

    /**
     * GET /api/hr/calendar/{country}/working-day?date=YYYY-MM-DD
     *
     * Evaluates whether a specific date is a working day and how many
     * standard hours apply (accounting for weekends, public holidays, and
     * Ramadan reduced hours).
     */
    public function workingDay(WorkingDayRequest $request, string $country): JsonResponse
    {
        $date = $request->input('date');
        $year = (int) CarbonImmutable::parse($date)->year;

        try {
            $calendar = match (strtolower($country)) {
                'eg'    => $this->egyptCalendar->forYear($year),
                'sa'    => $this->saudiCalendar->forYear($year),
                default => null,
            };
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($calendar === null) {
            return response()->json(['message' => "Unsupported country '{$country}'. Use 'eg' or 'sa'."], 422);
        }

        try {
            $ramadan = $this->ramadanService->forYear($year, strtoupper($country));
        } catch (InvalidArgumentException) {
            $ramadan = null;
        }

        $result = $this->evaluator->evaluate($date, $calendar, $ramadan);

        return response()->json($result->toArray());
    }
}
