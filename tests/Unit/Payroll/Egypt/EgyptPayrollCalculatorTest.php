<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll\Egypt;

use App\Services\Payroll\Egypt\EgyptPayrollCalculator;
use App\Services\Payroll\Egypt\EgyptPayrollConfig;
use App\Services\Payroll\Egypt\EtaIncomeTaxService;
use App\Services\Payroll\Egypt\LabourLaw14Service;
use App\Services\Payroll\Egypt\NosiContributionService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EgyptPayrollCalculatorTest extends TestCase
{
    private EgyptPayrollCalculator $calculator;
    private EgyptPayrollConfig     $config2025;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new EgyptPayrollCalculator(
            new NosiContributionService(),
            new EtaIncomeTaxService(),
            new LabourLaw14Service(),
        );
        $this->config2025 = EgyptPayrollConfig::forYear(2025);
    }

    /** @test */
    public function full_payroll_result_has_all_keys(): void
    {
        $result = $this->calculator->calculate(8_000.0, 50, $this->config2025);
        $arr    = $result->toArray();

        $this->assertArrayHasKey('gross_salary', $arr);
        $this->assertArrayHasKey('nosi', $arr);
        $this->assertArrayHasKey('income_tax', $arr);
        $this->assertArrayHasKey('training_fund_monthly', $arr);
        $this->assertArrayHasKey('net_salary', $arr);
        $this->assertArrayHasKey('total_employer_cost', $arr);
        $this->assertSame(2025, $arr['tax_year']);
    }

    /** @test */
    public function net_salary_equals_gross_minus_nosi_and_tax(): void
    {
        $result = $this->calculator->calculate(8_000.0, 50, $this->config2025);

        $expected = round(
            8_000.0 - $result->nosi->employeeAmount - $result->incomeTax->monthlyWithholding,
            2
        );
        $this->assertSame($expected, $result->netSalary());
    }

    /** @test */
    public function total_employer_cost_includes_all_employer_obligations(): void
    {
        $result = $this->calculator->calculate(8_000.0, 50, $this->config2025);

        $expected = round(
            8_000.0 + $result->nosi->totalEmployerAmount() + $result->trainingFundMonthly,
            2
        );
        $this->assertSame($expected, $result->totalEmployerCost());
    }

    /** @test */
    public function training_fund_is_zero_for_small_company(): void
    {
        $result = $this->calculator->calculate(8_000.0, companyHeadcount: 10, config: $this->config2025);

        $this->assertSame(0.0, $result->trainingFundMonthly);
    }

    /** @test */
    public function training_fund_is_nonzero_for_large_company(): void
    {
        $result = $this->calculator->calculate(8_000.0, companyHeadcount: 30, config: $this->config2025);

        $this->assertGreaterThan(0.0, $result->trainingFundMonthly);
    }

    /** @test */
    public function net_salary_is_less_than_gross(): void
    {
        $result = $this->calculator->calculate(10_000.0, 50, $this->config2025);

        $this->assertLessThan(10_000.0, $result->netSalary());
    }

    /** @test */
    public function total_employer_cost_is_greater_than_gross(): void
    {
        $result = $this->calculator->calculate(10_000.0, 50, $this->config2025);

        $this->assertGreaterThan(10_000.0, $result->totalEmployerCost());
    }

    /** @test */
    public function throws_on_negative_salary(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculate(-1_000.0, 10, $this->config2025);
    }

    /** @test */
    public function throws_on_zero_headcount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculate(8_000.0, 0, $this->config2025);
    }
}
