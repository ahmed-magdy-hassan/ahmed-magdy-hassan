<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr\Egypt;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\Egypt\EgyptPayrollRequest;
use App\Http\Requests\Hr\Egypt\EtaForm4Request;
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
     * Generate ETA Form 4 quarterly withholding data for a company.
     * GET /api/hr/egypt/companies/{company}/payroll/eta-form4?year=2026&quarter=2
     *
     * Only employees with a populated eta_tax_id are included — employees
     * without an ETA registration number are excluded from Form 4 filing.
     *
     * NOTE: Monthly withholding is calculated from each employee's current
     * gross salary. Once payroll run records are persisted, this should be
     * updated to read actuals from the payroll_runs table instead.
     */
    public function etaForm4(EtaForm4Request $request, int $company): JsonResponse
    {
        $year    = $request->year();
        $quarter = $request->quarter();
        $config  = EgyptPayrollConfig::forYear($year);
        $months  = $this->quarterMonths($year, $quarter);

        $headcount = Employee::where('company_id', $company)->count();

        $employees = Employee::where('company_id', $company)
            ->whereNotNull('eta_tax_id')
            ->get();

        $rows       = [];
        $grandTotal = 0.0;

        foreach ($employees as $employee) {
            $gross = (float) ($employee->nosi_insured_salary ?? $employee->basic_salary);

            $result             = $this->calculator->calculate($gross, max(1, $headcount), $config);
            $monthlyWithholding = $result->incomeTax->monthlyWithholding;
            $quarterlyTotal     = round($monthlyWithholding * 3, 2);
            $grandTotal        += $quarterlyTotal;

            $rows[] = [
                'eta_tax_id'           => $employee->eta_tax_id,
                'nosi_number'          => $employee->nosi_number,
                'gross_salary'         => $gross,
                'monthly_withholdings' => array_fill_keys($months, $monthlyWithholding),
                'quarterly_total'      => $quarterlyTotal,
            ];
        }

        [$periodFrom, $periodTo] = $this->quarterPeriod($year, $quarter);

        return response()->json([
            'company_id'   => $company,
            'year'         => $year,
            'quarter'      => $quarter,
            'period'       => ['from' => $periodFrom, 'to' => $periodTo],
            'generated_at' => now()->toIso8601String(),
            'employees'    => $rows,
            'summary'      => [
                'employee_count'  => count($rows),
                'quarterly_total' => round($grandTotal, 2),
            ],
        ]);
    }

    // -----------------------------------------------------------------------

    /** Returns the three YYYY-MM month strings belonging to a given quarter. */
    private function quarterMonths(int $year, int $quarter): array
    {
        $firstMonth = ($quarter - 1) * 3 + 1;

        return [
            sprintf('%d-%02d', $year, $firstMonth),
            sprintf('%d-%02d', $year, $firstMonth + 1),
            sprintf('%d-%02d', $year, $firstMonth + 2),
        ];
    }

    /** Returns [from, to] date strings (YYYY-MM-DD) for a quarter's calendar range. */
    private function quarterPeriod(int $year, int $quarter): array
    {
        $firstMonth = ($quarter - 1) * 3 + 1;
        $lastMonth  = $firstMonth + 2;

        $from = Carbon::createFromDate($year, $firstMonth, 1)->toDateString();
        $to   = Carbon::createFromDate($year, $lastMonth,  1)->endOfMonth()->toDateString();

        return [$from, $to];
    }
}
