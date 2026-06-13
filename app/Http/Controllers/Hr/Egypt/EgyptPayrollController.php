<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr\Egypt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\Egypt\EgyptPayrollRequest;
use App\Models\Employee;
use App\Services\Payroll\Egypt\EgyptPayrollCalculator;
use App\Services\Payroll\Egypt\EgyptPayrollConfig;
use App\Services\Payroll\Egypt\LabourLaw14Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

final class EgyptPayrollController extends Controller
{
    public function __construct(
        private readonly EgyptPayrollCalculator $calculator,
        private readonly LabourLaw14Service     $labourLaw14,
    ) {}

    /**
     * Calculate monthly Egypt payroll for a single employee (preview, no persistence).
     * POST /api/hr/egypt/employees/{employee}/payroll/calculate
     */
    public function calculate(EgyptPayrollRequest $request, Employee $employee): JsonResponse
    {
        $config = EgyptPayrollConfig::forYear(
            $request->validated('tax_year') ?? (int) date('Y')
        );

        $result = $this->calculator->calculate(
            grossSalary: (float) ($request->validated('gross_salary') ?? $employee->basic_salary),
            companyHeadcount: (int) ($request->validated('company_headcount') ?? 1),
            config: $config,
        );

        return response()->json($result->toArray());
    }

    /**
     * Return Labour Law No. 14/2025 entitlements for an employee.
     * GET /api/hr/egypt/employees/{employee}/labour-law-14
     */
    public function labourLaw14Entitlements(Employee $employee): JsonResponse
    {
        $yearsOfService = (int) Carbon::parse($employee->hire_date)->diffInYears(now());
        $insuredSalary  = (float) ($employee->nosi_insured_salary ?? $employee->basic_salary);

        return response()->json([
            'years_of_service'        => $yearsOfService,
            'annual_leave_days'       => $this->labourLaw14->leaveEntitlementDays($yearsOfService),
            'special_needs_leave_days'=> $this->labourLaw14->leaveEntitlementDays($yearsOfService, specialNeeds: true),
            'maternity_leave_days'    => $this->labourLaw14->maternityLeaveDays(),
            'maternity_max_times'     => $this->labourLaw14->maternityLeaveMaxOccurrences(),
            'notice_period_days'      => $this->labourLaw14->noticePeriodDays($yearsOfService),
            'annual_increment_amount' => $this->labourLaw14->annualIncrementAmount($insuredSalary),
            'annual_increment_rate'   => EgyptPayrollConfig::ANNUAL_INCREMENT_RATE,
            'effective_law_date'      => LabourLaw14Service::EFFECTIVE_DATE,
        ]);
    }

    /**
     * Generate ETA Form 4 quarterly data for a company.
     * GET /api/hr/egypt/companies/{company}/payroll/eta-form4?year=2025&quarter=1
     */
    public function etaForm4(int $company, int $year, int $quarter): JsonResponse
    {
        // Placeholder: full implementation requires iterating company employees,
        // summing monthly withholdings per employee for the quarter, and
        // producing the ETA Form 4 schema. Tracked in HRIST-191 follow-up.
        return response()->json([
            'company_id' => $company,
            'year'       => $year,
            'quarter'    => $quarter,
            'status'     => 'not_implemented',
            'note'       => 'ETA Form 4 generation requires company employee list — implement after employee model EG fields are migrated.',
        ], 501);
    }
}
