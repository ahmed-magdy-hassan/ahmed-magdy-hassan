<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Enums\Payroll\TerminationReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\EosbCalculationRequest;
use App\Models\Employee;
use App\Services\Calendar\HijriDateService;
use App\Services\Payroll\EosbCalculatorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

final class EosbController extends Controller
{
    public function __construct(
        private readonly EosbCalculatorService $calculator,
        private readonly HijriDateService $hijri,
    ) {}

    /**
     * Preview EOSB amount without persisting.
     * GET /api/hr/employees/{employee}/eosb/preview
     */
    public function preview(EosbCalculationRequest $request, Employee $employee): JsonResponse
    {
        $result = $this->calculator->calculate(
            monthlyWage: (float) $employee->basic_salary,
            startDate: $request->resolvedHireDateOverride() ?? Carbon::parse($employee->hire_date),
            endDate: $request->resolvedEndDate(),
            reason: TerminationReason::from($request->validated('termination_reason')),
        );

        $payload = $result->toArray();
        $payload['dates'] = $this->buildDateMeta($request, $employee);

        return response()->json($payload);
    }

    /**
     * Finalize EOSB: persist result to employee record for final settlement.
     * POST /api/hr/employees/{employee}/eosb/finalize
     */
    public function finalize(EosbCalculationRequest $request, Employee $employee): JsonResponse
    {
        $result = $this->calculator->calculate(
            monthlyWage: (float) $employee->basic_salary,
            startDate: $request->resolvedHireDateOverride() ?? Carbon::parse($employee->hire_date),
            endDate: $request->resolvedEndDate(),
            reason: TerminationReason::from($request->validated('termination_reason')),
        );

        $employee->update([
            'eosb_amount'             => $result->finalAmount,
            'eosb_termination_reason' => $result->terminationReason->value,
            'eosb_calculated_at'      => now(),
        ]);

        $payload = $result->toArray();
        $payload['dates'] = $this->buildDateMeta($request, $employee);

        return response()->json($payload, 201);
    }

    /**
     * Build a calendar meta block included in every EOSB response.
     * Exposes both Gregorian and Hijri representations of the key dates
     * so the frontend (Arabic/RTL) can display the calendar of the user's choice.
     */
    private function buildDateMeta(EosbCalculationRequest $request, Employee $employee): array
    {
        $hireDate = $request->resolvedHireDateOverride() ?? Carbon::parse($employee->hire_date);
        $endDate  = $request->resolvedEndDate();

        $hireHijri = $this->hijri->toHijri($hireDate);
        $endHijri  = $this->hijri->toHijri($endDate);

        return [
            'hire_date' => [
                'gregorian' => $hireDate->toDateString(),
                'hijri'     => $hireHijri->toString(),
                'hijri_ar'  => $hireHijri->format('ar'),
                'hijri_en'  => $hireHijri->format('en'),
            ],
            'end_date' => [
                'gregorian' => $endDate->toDateString(),
                'hijri'     => $endHijri->toString(),
                'hijri_ar'  => $endHijri->format('ar'),
                'hijri_en'  => $endHijri->format('en'),
            ],
        ];
    }
}
