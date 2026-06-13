<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects\Egypt;

use App\ValueObjects\Egypt\EgyptPayrollResult;
use App\ValueObjects\Egypt\EtaTaxWithholding;
use App\ValueObjects\Egypt\NosiContribution;
use PHPUnit\Framework\TestCase;

final class EgyptPayrollResultTest extends TestCase
{
    private EgyptPayrollResult $result;

    protected function setUp(): void
    {
        parent::setUp();

        $nosi = new NosiContribution(
            grossSalary: 8_000.0,
            insuredSalary: 8_000.0,
            employeeAmount: 880.0,
            employerBaseAmount: 1_500.0,
            workInjuryAmount: 80.0,
            employeeRate: 0.11,
            employerRate: 0.1875,
            workInjuryRate: 0.01,
        );

        $tax = new EtaTaxWithholding(
            monthlyGross: 8_000.0,
            nosiEmployeeDeduction: 880.0,
            personalExemptionMonthly: 1_666.67,
            monthlyTaxableIncome: 5_453.33,
            annualTaxableIncome: 65_440.0,
            annualTax: 3_066.0,
            monthlyWithholding: 255.5,
            bracketBreakdown: [],
        );

        $this->result = new EgyptPayrollResult(
            grossSalary: 8_000.0,
            nosi: $nosi,
            incomeTax: $tax,
            trainingFundMonthly: 4.5,
            taxYear: 2026,
        );
    }

    // -----------------------------------------------------------------------
    // netSalary()
    // -----------------------------------------------------------------------

    /** @test */
    public function net_salary_is_gross_minus_nosi_employee_minus_tax(): void
    {
        // 8000 - 880 (NOSI employee) - 255.5 (tax) = 6864.5
        $this->assertSame(6_864.5, $this->result->netSalary());
    }

    // -----------------------------------------------------------------------
    // totalEmployerCost()
    // -----------------------------------------------------------------------

    /** @test */
    public function total_employer_cost_includes_gross_nosi_employer_and_training_fund(): void
    {
        // 8000 + (1500 + 80) employer NOSI + 4.5 training = 9584.5
        $this->assertSame(9_584.5, $this->result->totalEmployerCost());
    }

    // -----------------------------------------------------------------------
    // toArray()
    // -----------------------------------------------------------------------

    /** @test */
    public function to_array_has_all_top_level_keys(): void
    {
        $arr = $this->result->toArray();

        $this->assertArrayHasKey('tax_year', $arr);
        $this->assertArrayHasKey('gross_salary', $arr);
        $this->assertArrayHasKey('nosi', $arr);
        $this->assertArrayHasKey('income_tax', $arr);
        $this->assertArrayHasKey('training_fund_monthly', $arr);
        $this->assertArrayHasKey('net_salary', $arr);
        $this->assertArrayHasKey('total_employer_cost', $arr);
    }

    /** @test */
    public function to_array_nosi_and_income_tax_are_arrays(): void
    {
        $arr = $this->result->toArray();

        $this->assertIsArray($arr['nosi']);
        $this->assertIsArray($arr['income_tax']);
    }

    /** @test */
    public function to_array_computed_values_match_methods(): void
    {
        $arr = $this->result->toArray();

        $this->assertSame($this->result->netSalary(),          $arr['net_salary']);
        $this->assertSame($this->result->totalEmployerCost(),  $arr['total_employer_cost']);
        $this->assertSame(2026,                                $arr['tax_year']);
    }
}
