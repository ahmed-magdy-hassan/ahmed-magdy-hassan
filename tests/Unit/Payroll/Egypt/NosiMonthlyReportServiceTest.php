<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll\Egypt;

use App\DTOs\Egypt\EgyptEmployeePayrollInput;
use App\Services\Payroll\Egypt\EgyptPayrollConfig;
use App\Services\Payroll\Egypt\NosiContributionService;
use App\Services\Payroll\Egypt\NosiMonthlyReportService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NosiMonthlyReportServiceTest extends TestCase
{
    private NosiMonthlyReportService $service;
    private EgyptPayrollConfig       $config2026;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service    = new NosiMonthlyReportService(new NosiContributionService());
        $this->config2026 = EgyptPayrollConfig::forYear(2026);
    }

    private function employee(string $id = 'EMP-001', float $gross = 10_000.0): EgyptEmployeePayrollInput
    {
        return new EgyptEmployeePayrollInput(
            employeeId: $id,
            name:       'Ahmed Ali',
            nationalId: '29001011234567',
            nosiNumber: 'NOSI-001',
            grossSalary: $gross,
        );
    }

    /** @test */
    public function single_employee_report_has_correct_employee_record(): void
    {
        $report = $this->service->generate([$this->employee()], 2026, 6, $this->config2026);

        $this->assertCount(1, $report->employees);
        $this->assertSame('EMP-001', $report->employees[0]->employeeId);
        $this->assertSame(10_000.0, $report->employees[0]->grossSalary);
    }

    /** @test */
    public function employee_contribution_is_11_percent_of_insured_salary(): void
    {
        $report = $this->service->generate([$this->employee(gross: 8_000.0)], 2026, 6, $this->config2026);
        // 8000 is within NOSI cap, so insured = 8000; 11% = 880
        $this->assertEqualsWithDelta(880.0, $report->employees[0]->employeeContribution, 0.01);
    }

    /** @test */
    public function employer_base_contribution_is_18_75_percent(): void
    {
        $report = $this->service->generate([$this->employee(gross: 8_000.0)], 2026, 6, $this->config2026);
        $this->assertEqualsWithDelta(1_500.0, $report->employees[0]->employerBaseContribution, 0.01);
    }

    /** @test */
    public function work_injury_contribution_is_1_percent(): void
    {
        $report = $this->service->generate([$this->employee(gross: 8_000.0)], 2026, 6, $this->config2026);
        $this->assertEqualsWithDelta(80.0, $report->employees[0]->workInjuryContribution, 0.01);
    }

    /** @test */
    public function grand_total_sums_employee_and_employer(): void
    {
        $report = $this->service->generate([$this->employee(gross: 8_000.0)], 2026, 6, $this->config2026);
        // 880 + 1500 + 80 = 2460
        $this->assertEqualsWithDelta(2_460.0, $report->grandTotalContribution, 0.05);
    }

    /** @test */
    public function totals_sum_across_multiple_employees(): void
    {
        $employees = [
            $this->employee('A', 8_000.0),
            $this->employee('B', 8_000.0),
        ];

        $report = $this->service->generate($employees, 2026, 6, $this->config2026);

        $this->assertEqualsWithDelta(16_000.0, $report->totalGrossSalary, 0.01);
        $this->assertEqualsWithDelta(1_760.0,  $report->totalEmployeeContribution, 0.05);
        $this->assertEqualsWithDelta(4_920.0,  $report->grandTotalContribution, 0.1);
    }

    /** @test */
    public function period_label_contains_month_name_and_year(): void
    {
        $report = $this->service->generate([$this->employee()], 2026, 6, $this->config2026);
        $this->assertStringContainsString('June', $report->periodLabel);
        $this->assertStringContainsString('2026', $report->periodLabel);
    }

    /** @test */
    public function due_date_is_15th_of_following_month(): void
    {
        $report = $this->service->generate([$this->employee()], 2026, 6, $this->config2026);
        $this->assertSame('2026-07-15', $report->dueDate()->toDateString());
    }

    /** @test */
    public function due_date_wraps_to_next_year_for_december(): void
    {
        $report = $this->service->generate([$this->employee()], 2026, 12, $this->config2026);
        $this->assertSame('2027-01-15', $report->dueDate()->toDateString());
    }

    /** @test */
    public function throws_on_invalid_month(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->generate([$this->employee()], 2026, 0);
    }

    /** @test */
    public function throws_on_empty_employees(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->generate([], 2026, 6);
    }

    /** @test */
    public function salary_above_nosi_cap_is_clamped(): void
    {
        // 2026 cap max = 16,441; use 50,000 to test clamping
        $report = $this->service->generate([$this->employee(gross: 50_000.0)], 2026, 6, $this->config2026);
        $employee = $report->employees[0];

        $this->assertLessThan(50_000.0, $employee->insuredSalary);
        $this->assertSame(50_000.0, $employee->grossSalary);
    }

    /** @test */
    public function to_array_contains_all_required_keys(): void
    {
        $arr = $this->service->generate([$this->employee()], 2026, 6, $this->config2026)->toArray();

        foreach (['year', 'month', 'period_label', 'due_date', 'employee_count',
                  'total_gross_salary', 'total_insured_salary', 'total_employee_contribution',
                  'total_employer_base_contribution', 'total_work_injury_contribution',
                  'total_employer_contribution', 'grand_total_contribution', 'employees'] as $key) {
            $this->assertArrayHasKey($key, $arr);
        }
    }
}
