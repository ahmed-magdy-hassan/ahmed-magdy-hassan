<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll\Egypt;

use App\DTOs\Egypt\EgyptEmployeePayrollInput;
use App\Services\Payroll\Egypt\EgyptPayrollCalculator;
use App\Services\Payroll\Egypt\EgyptPayrollConfig;
use App\Services\Payroll\Egypt\EgyptPayrollRunService;
use App\Services\Payroll\Egypt\EtaIncomeTaxService;
use App\Services\Payroll\Egypt\LabourLaw14Service;
use App\Services\Payroll\Egypt\NosiContributionService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EgyptPayrollRunServiceTest extends TestCase
{
    private EgyptPayrollRunService $service;
    private EgyptPayrollConfig     $config2026;

    protected function setUp(): void
    {
        parent::setUp();
        $calculator       = new EgyptPayrollCalculator(
            new NosiContributionService(),
            new EtaIncomeTaxService(),
            new LabourLaw14Service(),
        );
        $this->service    = new EgyptPayrollRunService($calculator);
        $this->config2026 = EgyptPayrollConfig::forYear(2026);
    }

    private function employee(
        string $id        = 'EMP-001',
        float  $gross     = 10_000.0,
        string $nationalId = '29001011234567',
    ): EgyptEmployeePayrollInput {
        return new EgyptEmployeePayrollInput(
            employeeId: $id,
            name:       'Test Employee',
            nationalId: $nationalId,
            nosiNumber: '1234567890',
            grossSalary: $gross,
        );
    }

    /** @test */
    public function single_employee_run_returns_correct_structure(): void
    {
        $result = $this->service->run(
            [$this->employee()],
            year: 2026, month: 6,
            config: $this->config2026,
        );

        $this->assertSame(2026, $result->year);
        $this->assertSame(6, $result->month);
        $this->assertSame(1, $result->employeeCount());
        $this->assertSame(10_000.0, $result->totalGrossSalary);
    }

    /** @test */
    public function totals_sum_correctly_across_multiple_employees(): void
    {
        $employees = [
            $this->employee('EMP-001', 8_000.0),
            $this->employee('EMP-002', 12_000.0, '29001011234568'),
        ];

        $result = $this->service->run($employees, 2026, 6, $this->config2026);

        $this->assertSame(2, $result->employeeCount());
        $this->assertSame(20_000.0, $result->totalGrossSalary);
        $this->assertGreaterThan(0.0, $result->totalNetSalary);
        $this->assertGreaterThan(0.0, $result->totalNosiEmployee);
        $this->assertGreaterThan(0.0, $result->totalNosiEmployer);
    }

    /** @test */
    public function employer_cost_exceeds_gross_due_to_nosi_employer_share(): void
    {
        $result = $this->service->run([$this->employee()], 2026, 6, $this->config2026);

        $this->assertGreaterThan($result->totalGrossSalary, $result->totalEmployerCost);
    }

    /** @test */
    public function net_plus_deductions_equals_gross(): void
    {
        $result = $this->service->run([$this->employee()], 2026, 6, $this->config2026);

        $expected = round(
            $result->totalNetSalary + $result->totalNosiEmployee + $result->totalTaxWithheld,
            2
        );
        $this->assertEqualsWithDelta($result->totalGrossSalary, $expected, 0.05);
    }

    /** @test */
    public function training_fund_applied_for_large_company(): void
    {
        // ≥30 employees triggers training fund
        $employees = array_map(
            fn($i) => $this->employee("EMP-{$i}", 8_000.0, str_pad((string)($29001011234567 + $i), 14, '0', STR_PAD_LEFT)),
            range(1, 30)
        );

        $result = $this->service->run($employees, 2026, 6, $this->config2026);

        $this->assertGreaterThan(0.0, $result->totalTrainingFund);
    }

    /** @test */
    public function training_fund_is_zero_for_small_company(): void
    {
        $result = $this->service->run([$this->employee()], 2026, 6, $this->config2026);

        $this->assertSame(0.0, $result->totalTrainingFund);
    }

    /** @test */
    public function throws_on_empty_employees(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one employee');

        $this->service->run([], 2026, 6, $this->config2026);
    }

    /** @test */
    public function throws_on_invalid_month(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Month must be 1–12');

        $this->service->run([$this->employee()], 2026, 13, $this->config2026);
    }

    /** @test */
    public function to_array_has_expected_top_level_keys(): void
    {
        $arr = $this->service
            ->run([$this->employee()], 2026, 6, $this->config2026)
            ->toArray();

        foreach (['year', 'month', 'tax_year', 'employee_count', 'totals', 'employees'] as $key) {
            $this->assertArrayHasKey($key, $arr);
        }
    }

    /** @test */
    public function to_array_totals_block_has_all_keys(): void
    {
        $totals = $this->service
            ->run([$this->employee()], 2026, 6, $this->config2026)
            ->toArray()['totals'];

        foreach (['gross_salary', 'net_salary', 'nosi_employee', 'nosi_employer',
                  'tax_withheld', 'training_fund', 'employer_cost'] as $key) {
            $this->assertArrayHasKey($key, $totals);
        }
    }

    /** @test */
    public function each_employee_row_contains_input_and_payroll(): void
    {
        $arr       = $this->service->run([$this->employee()], 2026, 6, $this->config2026)->toArray();
        $firstRow  = $arr['employees'][0];

        $this->assertArrayHasKey('employee', $firstRow);
        $this->assertArrayHasKey('payroll', $firstRow);
        $this->assertSame('EMP-001', $firstRow['employee']['employee_id']);
    }
}
