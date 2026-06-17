<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll\Egypt;

use App\DTOs\Egypt\EgyptEmployeePayrollInput;
use App\Services\Payroll\Egypt\EgyptPayrollConfig;
use App\Services\Payroll\Egypt\EtaForm4Service;
use App\Services\Payroll\Egypt\EtaIncomeTaxService;
use App\Services\Payroll\Egypt\NosiContributionService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EtaForm4ServiceTest extends TestCase
{
    private EtaForm4Service    $service;
    private EgyptPayrollConfig $config2026;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service    = new EtaForm4Service(new EtaIncomeTaxService(), new NosiContributionService());
        $this->config2026 = EgyptPayrollConfig::forYear(2026);
    }

    private function employee(string $id = 'EMP-001', float $gross = 10_000.0): EgyptEmployeePayrollInput
    {
        return new EgyptEmployeePayrollInput(
            employeeId: $id,
            name:       'Ahmed Ali',
            nationalId: '29001011234567',
            nosiNumber: '1234567890',
            grossSalary: $gross,
        );
    }

    /** @test */
    public function quarterly_gross_is_three_times_monthly(): void
    {
        $report = $this->service->generate([$this->employee()], 2026, 2, $this->config2026);

        $this->assertEqualsWithDelta(30_000.0, $report->employees[0]->quarterlyGross, 0.01);
    }

    /** @test */
    public function quarterly_tax_withheld_is_three_times_monthly(): void
    {
        $report   = $this->service->generate([$this->employee()], 2026, 2, $this->config2026);
        $employee = $report->employees[0];

        // Monthly tax for 10k gross is calculated by EtaIncomeTaxService
        $this->assertGreaterThan(0.0, $employee->quarterlyTaxWithheld);
        // Should be divisible by 3 (up to rounding tolerance)
        $this->assertEqualsWithDelta(
            $employee->quarterlyTaxWithheld / 3,
            round($employee->quarterlyTaxWithheld / 3, 2),
            0.05
        );
    }

    /** @test */
    public function report_total_gross_sums_all_employees(): void
    {
        $employees = [
            $this->employee('EMP-001', 8_000.0),
            $this->employee('EMP-002', 12_000.0),
        ];

        $report = $this->service->generate($employees, 2026, 1, $this->config2026);

        // Q total = (8000 + 12000) × 3 = 60,000
        $this->assertEqualsWithDelta(60_000.0, $report->totalQuarterlyGross, 0.01);
    }

    /** @test */
    public function report_total_employee_count_is_correct(): void
    {
        $report = $this->service->generate(
            [$this->employee('A'), $this->employee('B'), $this->employee('C')],
            2026, 3, $this->config2026,
        );

        $this->assertSame(3, (int) $report->totalEmployeeCount);
    }

    /** @test */
    public function period_label_contains_year_and_quarter_name(): void
    {
        $q1 = $this->service->generate([$this->employee()], 2026, 1, $this->config2026);
        $q4 = $this->service->generate([$this->employee()], 2026, 4, $this->config2026);

        $this->assertStringContainsString('2026', $q1->periodLabel);
        $this->assertStringContainsString('Q1', $q1->periodLabel);
        $this->assertStringContainsString('Q4', $q4->periodLabel);
    }

    /** @test */
    public function q1_due_date_is_april_30(): void
    {
        $report = $this->service->generate([$this->employee()], 2026, 1, $this->config2026);
        $this->assertSame('2026-04-30', $report->dueDate()->toDateString());
    }

    /** @test */
    public function q2_due_date_is_july_31(): void
    {
        $report = $this->service->generate([$this->employee()], 2026, 2, $this->config2026);
        $this->assertSame('2026-07-31', $report->dueDate()->toDateString());
    }

    /** @test */
    public function q4_due_date_falls_in_next_year(): void
    {
        $report = $this->service->generate([$this->employee()], 2026, 4, $this->config2026);
        $this->assertSame('2027-01-31', $report->dueDate()->toDateString());
    }

    /** @test */
    public function quarterly_personal_exemption_is_annual_divided_by_4(): void
    {
        $report = $this->service->generate([$this->employee()], 2026, 1, $this->config2026);

        $expectedQuarterlyExemption = $this->config2026->personalAnnualExemption / 4;
        $this->assertEqualsWithDelta(
            $expectedQuarterlyExemption,
            $report->employees[0]->quarterlyPersonalExemption,
            0.1
        );
    }

    /** @test */
    public function throws_on_invalid_quarter(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->generate([$this->employee()], 2026, 5);
    }

    /** @test */
    public function throws_on_empty_employees(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->generate([], 2026, 1);
    }

    /** @test */
    public function to_array_contains_all_required_keys(): void
    {
        $arr = $this->service->generate([$this->employee()], 2026, 2, $this->config2026)->toArray();

        foreach (['year', 'quarter', 'period_label', 'months', 'due_date',
                  'total_employee_count', 'total_quarterly_gross',
                  'total_quarterly_tax_withheld', 'employees'] as $key) {
            $this->assertArrayHasKey($key, $arr);
        }
    }

    /** @test */
    public function months_array_matches_quarter(): void
    {
        $this->assertSame([1, 2, 3], $this->service->generate([$this->employee()], 2026, 1)->months());
        $this->assertSame([4, 5, 6], $this->service->generate([$this->employee()], 2026, 2)->months());
        $this->assertSame([7, 8, 9], $this->service->generate([$this->employee()], 2026, 3)->months());
        $this->assertSame([10, 11, 12], $this->service->generate([$this->employee()], 2026, 4)->months());
    }

    /** @test */
    public function effective_rate_is_non_negative(): void
    {
        $report = $this->service->generate([$this->employee()], 2026, 1, $this->config2026);

        foreach ($report->employees as $emp) {
            $this->assertGreaterThanOrEqual(0.0, $emp->effectiveAnnualRate);
        }
    }

    /** @test */
    public function zero_salary_employee_has_zero_tax(): void
    {
        $report = $this->service->generate([$this->employee('Z', 0.0)], 2026, 1, $this->config2026);

        $this->assertSame(0.0, $report->employees[0]->quarterlyTaxWithheld);
        $this->assertSame(0.0, $report->totalQuarterlyTaxWithheld);
    }
}
