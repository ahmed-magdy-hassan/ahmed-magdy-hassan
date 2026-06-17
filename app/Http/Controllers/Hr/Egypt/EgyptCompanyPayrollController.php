<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr\Egypt;

use App\DTOs\Egypt\EgyptEmployeePayrollInput;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\Egypt\CompanyPayrollRunRequest;
use App\Http\Requests\Hr\Egypt\EtaForm4Request;
use App\Http\Requests\Hr\Egypt\NosiMonthlyReportRequest;
use App\Models\Company;
use App\Services\Payroll\Egypt\EgyptPayrollConfig;
use App\Services\Payroll\Egypt\EgyptPayrollRunService;
use App\Services\Payroll\Egypt\EtaForm4Service;
use App\Services\Payroll\Egypt\NosiMonthlyReportService;
use Illuminate\Http\JsonResponse;

/**
 * Company-level Egypt payroll operations.
 *
 * Routes (register in routes/api.php):
 *   POST  /api/hr/egypt/companies/{company}/payroll/run
 *   GET   /api/hr/egypt/companies/{company}/payroll/eta-form4
 *   GET   /api/hr/egypt/companies/{company}/payroll/nosi-report
 */
final class EgyptCompanyPayrollController extends Controller
{
    public function __construct(
        private readonly EgyptPayrollRunService    $runService,
        private readonly EtaForm4Service           $etaForm4Service,
        private readonly NosiMonthlyReportService  $nosiReportService,
    ) {}

    /**
     * Run monthly payroll for every employee passed in the request body.
     * POST /api/hr/egypt/companies/{company}/payroll/run
     *
     * No DB writes — returns a full payroll preview for confirmation.
     *
     * Request body: CompanyPayrollRunRequest
     *
     * 201 response:
     * {
     *   "year": 2026,
     *   "month": 6,
     *   "tax_year": 2026,
     *   "employee_count": 3,
     *   "totals": {
     *     "gross_salary": 36000.00,
     *     "net_salary": 28500.00,
     *     "nosi_employee": 3960.00,
     *     "nosi_employer": 6750.00,
     *     "tax_withheld": 765.00,
     *     "training_fund": 13.50,
     *     "employer_cost": 43513.50
     *   },
     *   "employees": [...]
     * }
     */
    public function run(CompanyPayrollRunRequest $request, Company $company): JsonResponse
    {
        $validated = $request->validated();
        $config    = EgyptPayrollConfig::forYear($validated['tax_year'] ?? $validated['year']);

        $inputs = array_map(
            fn(array $e) => new EgyptEmployeePayrollInput(
                employeeId:             $e['employee_id'],
                name:                   $e['name'],
                nationalId:             $e['national_id'],
                nosiNumber:             $e['nosi_number'],
                grossSalary:            (float) $e['gross_salary'],
                insuredSalaryOverride:  isset($e['insured_salary']) ? (float) $e['insured_salary'] : null,
                specialNeeds:           (bool) ($e['special_needs'] ?? false),
                hireDate:               $e['hire_date'] ?? null,
            ),
            $validated['employees'],
        );

        $result = $this->runService->run(
            employees: $inputs,
            year:      $validated['year'],
            month:     $validated['month'],
            config:    $config,
        );

        return response()->json($result->toArray(), 201);
    }

    /**
     * Generate ETA Form 4 quarterly withholding return for the company.
     * GET /api/hr/egypt/companies/{company}/payroll/eta-form4?year=2026&quarter=2
     *
     * This endpoint reads each active employee's current salary from the DB
     * and computes their quarterly figures assuming a constant monthly salary.
     * For variable salaries, front-end should use the /run endpoint with 3 months
     * of data and aggregate client-side.
     *
     * 200 response:
     * {
     *   "year": 2026,
     *   "quarter": 2,
     *   "period_label": "Q2 (Apr–Jun) 2026",
     *   "months": [4, 5, 6],
     *   "due_date": "2026-07-31",
     *   "total_employee_count": 3,
     *   "total_quarterly_gross": 108000.00,
     *   "total_quarterly_tax_withheld": 2295.00,
     *   "employees": [...]
     * }
     */
    public function etaForm4(EtaForm4Request $request, Company $company): JsonResponse
    {
        $year    = (int) $request->validated('year');
        $quarter = (int) $request->validated('quarter');
        $config  = EgyptPayrollConfig::forYear($year);

        // Load active employees from the company — requires Egypt fields migration
        /** @var \Illuminate\Database\Eloquent\Collection $activeEmployees */
        $activeEmployees = $company->employees()
            ->whereNotNull('national_id')
            ->whereNotNull('nosi_number')
            ->where('status', 'active')
            ->get();

        $inputs = $activeEmployees->map(
            fn($e) => new EgyptEmployeePayrollInput(
                employeeId: (string) $e->id,
                name:       $e->full_name,
                nationalId: $e->national_id,
                nosiNumber: $e->nosi_number,
                grossSalary: (float) $e->basic_salary,
                insuredSalaryOverride: $e->nosi_insured_salary ? (float) $e->nosi_insured_salary : null,
            )
        )->all();

        if (empty($inputs)) {
            return response()->json([
                'message' => 'No active employees with Egypt payroll fields found for this company.',
                'company_id' => $company->id,
                'year'    => $year,
                'quarter' => $quarter,
                'employees' => [],
            ], 200);
        }

        $report = $this->etaForm4Service->generate($inputs, $year, $quarter, $config);

        return response()->json($report->toArray());
    }

    /**
     * Generate NOSI Form 1 monthly contribution declaration.
     * GET /api/hr/egypt/companies/{company}/payroll/nosi-report?year=2026&month=6
     *
     * 200 response:
     * {
     *   "year": 2026,
     *   "month": 6,
     *   "period_label": "June 2026",
     *   "due_date": "2026-07-15",
     *   "employee_count": 3,
     *   "total_gross_salary": 36000.00,
     *   "total_insured_salary": 36000.00,
     *   "total_employee_contribution": 3960.00,
     *   "total_employer_base_contribution": 6750.00,
     *   "total_work_injury_contribution": 360.00,
     *   "total_employer_contribution": 7110.00,
     *   "grand_total_contribution": 11070.00,
     *   "employees": [...]
     * }
     */
    public function nosiMonthlyReport(NosiMonthlyReportRequest $request, Company $company): JsonResponse
    {
        $year  = (int) $request->validated('year');
        $month = (int) $request->validated('month');
        $config = EgyptPayrollConfig::forYear($year);

        $activeEmployees = $company->employees()
            ->whereNotNull('nosi_number')
            ->where('status', 'active')
            ->get();

        $inputs = $activeEmployees->map(
            fn($e) => new EgyptEmployeePayrollInput(
                employeeId: (string) $e->id,
                name:       $e->full_name,
                nationalId: $e->national_id ?? '',
                nosiNumber: $e->nosi_number,
                grossSalary: (float) $e->basic_salary,
                insuredSalaryOverride: $e->nosi_insured_salary ? (float) $e->nosi_insured_salary : null,
            )
        )->all();

        if (empty($inputs)) {
            return response()->json([
                'message'    => 'No active NOSI-registered employees found for this company.',
                'company_id' => $company->id,
                'year'       => $year,
                'month'      => $month,
                'employees'  => [],
            ], 200);
        }

        $report = $this->nosiReportService->generate($inputs, $year, $month, $config);

        return response()->json($report->toArray());
    }
}
