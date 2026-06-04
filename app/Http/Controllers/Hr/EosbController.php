<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Enums\Payroll\TerminationReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\EosbCalculationRequest;
use App\Models\Employee;
use App\Services\Payroll\EosbCalculatorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

final class EosbController extends Controller
{
    public function __construct(private readonly EosbCalculatorService $calculator) {}

    /**
     * Preview EOSB amount without persisting.
     * GET /api/hr/employees/{employee}/eosb/preview
     */
    public function preview(EosbCalculationRequest $request, Employee $employee): JsonResponse
    {
        $result = $this->calculator->calculate(
            monthlyWage: (float) $employee->basic_salary,
            startDate: Carbon::parse($employee->hire_date),
            endDate: Carbon::parse($request->validated('end_date')),
            reason: TerminationReason::from($request->validated('termination_reason')),
        );

        return response()->json($result->toArray());
    }

    /**
     * Finalize EOSB: persist result to employee record for final settlement.
     * POST /api/hr/employees/{employee}/eosb/finalize
     */
    public function finalize(EosbCalculationRequest $request, Employee $employee): JsonResponse
    {
        $result = $this->calculator->calculate(
            monthlyWage: (float) $employee->basic_salary,
            startDate: Carbon::parse($employee->hire_date),
            endDate: Carbon::parse($request->validated('end_date')),
            reason: TerminationReason::from($request->validated('termination_reason')),
        );

        $employee->update([
            'eosb_amount'             => $result->finalAmount,
            'eosb_termination_reason' => $result->terminationReason->value,
            'eosb_calculated_at'      => now(),
        ]);

        return response()->json($result->toArray(), 201);
    }
}
