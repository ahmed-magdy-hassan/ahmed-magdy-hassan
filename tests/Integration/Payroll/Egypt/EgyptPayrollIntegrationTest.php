<?php

declare(strict_types=1);

namespace Tests\Integration\Payroll\Egypt;

use App\DTOs\Egypt\EgyptEmployeePayrollInput;
use App\Services\Payroll\Egypt\EgyptPayrollCalculator;
use App\Services\Payroll\Egypt\EgyptPayrollConfig;
use App\Services\Payroll\Egypt\EgyptPayrollRunService;
use App\Services\Payroll\Egypt\EtaForm4Service;
use App\Services\Payroll\Egypt\EtaIncomeTaxService;
use App\Services\Payroll\Egypt\LabourLaw14Service;
use App\Services\Payroll\Egypt\NosiContributionService;
use App\Services\Payroll\Egypt\NosiMonthlyReportService;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests — exercise all Egypt payroll services working together
 * with real objects (no mocks) and verify the combined output is consistent.
 *
 * These tests catch regressions that isolated unit tests may miss: e.g. the
 * NosiContributionService passing a clamped insured salary to EtaIncomeTaxService
 * that then produces a different personal-exemption calculation.
 */
final class EgyptPayrollIntegrationTest extends TestCase
{
    private EgyptPayrollCalculator   $calculator;
    private EgyptPayrollRunService   $runService;
    private EtaForm4Service          $etaForm4Service;
    private NosiMonthlyReportService $nosiReportService;
    private EgyptPayrollConfig       $config2026;

    protected function setUp(): void
    {
        parent::setUp();

        $nosi    = new NosiContributionService();
        $tax     = new EtaIncomeTaxService();
        $labour  = new LabourLaw14Service();

        $this->calculator        = new EgyptPayrollCalculator($nosi, $tax, $labour);
        $this->runService        = new EgyptPayrollRunService($this->calculator);
        $this->etaForm4Service   = new EtaForm4Service($tax, $nosi);
        $this->nosiReportService = new NosiMonthlyReportService($nosi);
        $this->config2026        = EgyptPayrollConfig::forYear(2026);
    }

    private function employees(): array
    {
        return [
            new EgyptEmployeePayrollInput('EMP-001', 'Ahmed Ali',   '29001011234567', 'NOSI-001', 8_000.0),
            new EgyptEmployeePayrollInput('EMP-002', 'Sara Hassan', '29001011234568', 'NOSI-002', 12_000.0),
            new EgyptEmployeePayrollInput('EMP-003', 'Mona Saad',   '29001011234569', 'NOSI-003', 30_000.0),
        ];
    }

    // -----------------------------------------------------------------------
    // Payroll run ↔ NOSI report consistency
    // -----------------------------------------------------------------------

    /** @test */
    public function payroll_run_nosi_totals_match_standalone_nosi_report(): void
    {
        $employees  = $this->employees();
        $payrollRun = $this->runService->run($employees, 2026, 6, $this->config2026);
        $nosiReport = $this->nosiReportService->generate($employees, 2026, 6, $this->config2026);

        $this->assertEqualsWithDelta(
            $payrollRun->totalNosiEmployee,
            $nosiReport->totalEmployeeContribution,
            0.05,
            'NOSI employee totals differ between payroll run and NOSI report',
        );

        $this->assertEqualsWithDelta(
            $payrollRun->totalNosiEmployer,
            $nosiReport->totalEmployerContribution,
            0.05,
            'NOSI employer totals differ between payroll run and NOSI report',
        );
    }

    // -----------------------------------------------------------------------
    // Payroll run ↔ ETA Form 4 consistency
    // -----------------------------------------------------------------------

    /** @test */
    public function payroll_run_monthly_tax_times_3_matches_form4_quarter(): void
    {
        $employees  = $this->employees();
        $payrollRun = $this->runService->run($employees, 2026, 6, $this->config2026);
        $form4      = $this->etaForm4Service->generate($employees, 2026, 2, $this->config2026);

        // Q2 = Apr+May+Jun; with constant salary, total = monthly × 3
        $this->assertEqualsWithDelta(
            round($payrollRun->totalTaxWithheld * 3, 2),
            $form4->totalQuarterlyTaxWithheld,
            0.1,
            'Form 4 quarterly tax does not equal 3× monthly payroll run tax',
        );
    }

    // -----------------------------------------------------------------------
    // Calculator → RunService consistency (single employee)
    // -----------------------------------------------------------------------

    /** @test */
    public function run_service_matches_single_calculator_call(): void
    {
        $input   = $this->employees()[0];
        $direct  = $this->calculator->calculate($input->grossSalary, 1, $this->config2026);
        $runResult = $this->runService->run([$input], 2026, 6, $this->config2026);

        $this->assertEqualsWithDelta(
            $direct->netSalary(),
            $runResult->totalNetSalary,
            0.01,
        );
    }

    // -----------------------------------------------------------------------
    // Salary above NOSI cap — insured salary clamped consistently everywhere
    // -----------------------------------------------------------------------

    /** @test */
    public function high_salary_uses_capped_insured_salary_in_all_services(): void
    {
        $highEarner = [new EgyptEmployeePayrollInput('HI', 'High', '00000000000000', 'N', 80_000.0)];
        $cap        = $this->config2026->nosiMaxInsuredSalary;  // 16,441

        $nosiReport = $this->nosiReportService->generate($highEarner, 2026, 6, $this->config2026);
        $form4      = $this->etaForm4Service->generate($highEarner, 2026, 1, $this->config2026);

        $this->assertEqualsWithDelta(
            $cap * EgyptPayrollConfig::NOSI_EMPLOYEE_RATE,
            $nosiReport->employees[0]->employeeContribution,
            0.05,
            'NOSI employee contribution should be based on capped insured salary',
        );

        // Quarterly NOSI deduction in Form 4 = capped monthly NOSI × 3
        $this->assertEqualsWithDelta(
            round($cap * EgyptPayrollConfig::NOSI_EMPLOYEE_RATE * 3, 2),
            $form4->employees[0]->quarterlyNosiDeduction,
            0.1,
            'Form 4 NOSI deduction should be capped at insured salary cap',
        );
    }

    // -----------------------------------------------------------------------
    // Labour Law entitlements consistent with service years
    // -----------------------------------------------------------------------

    /** @test */
    public function leave_entitlement_follows_law14_service_year_rules(): void
    {
        $labour = new LabourLaw14Service();

        $this->assertSame(15, $labour->leaveEntitlementDays(0));   // Year 1
        $this->assertSame(15, $labour->leaveEntitlementDays(1));   // Year 1–2
        $this->assertSame(21, $labour->leaveEntitlementDays(2));   // Year 2+
        $this->assertSame(45, $labour->leaveEntitlementDays(5, specialNeeds: true));
    }

    // -----------------------------------------------------------------------
    // Training-fund threshold integration
    // -----------------------------------------------------------------------

    /** @test */
    public function training_fund_kicks_in_at_exactly_30_employees(): void
    {
        $make = fn(int $n) => array_map(
            fn($i) => new EgyptEmployeePayrollInput("E{$i}", "Emp {$i}", str_pad((string) $i, 14, '2'), "N{$i}", 5_000.0),
            range(1, $n)
        );

        $below = $this->runService->run($make(29), 2026, 6, $this->config2026);
        $at    = $this->runService->run($make(30), 2026, 6, $this->config2026);

        $this->assertSame(0.0, $below->totalTrainingFund);
        $this->assertGreaterThan(0.0, $at->totalTrainingFund);
    }

    // -----------------------------------------------------------------------
    // Gross/net reconciliation across entire company
    // -----------------------------------------------------------------------

    /** @test */
    public function company_gross_equals_net_plus_employee_deductions(): void
    {
        $run = $this->runService->run($this->employees(), 2026, 6, $this->config2026);

        $reconstructedGross = round(
            $run->totalNetSalary
            + $run->totalNosiEmployee
            + $run->totalTaxWithheld,
            2
        );

        $this->assertEqualsWithDelta(
            $run->totalGrossSalary,
            $reconstructedGross,
            0.10,
            'Sum of net + NOSI employee + tax should equal gross',
        );
    }
}
