<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects\Egypt;

use App\ValueObjects\Egypt\EtaTaxWithholding;
use PHPUnit\Framework\TestCase;

final class EtaTaxWithholdingTest extends TestCase
{
    private function make(
        float $annualTaxableIncome,
        float $annualTax,
        float $monthlyWithholding,
    ): EtaTaxWithholding {
        return new EtaTaxWithholding(
            monthlyGross: 8_000.0,
            nosiEmployeeDeduction: 880.0,
            personalExemptionMonthly: 1_666.67,
            monthlyTaxableIncome: 5_453.33,
            annualTaxableIncome: $annualTaxableIncome,
            annualTax: $annualTax,
            monthlyWithholding: $monthlyWithholding,
            bracketBreakdown: [
                ['from' => 0.0, 'to' => 40_000.0, 'rate' => 0.0, 'taxable' => 40_000.0, 'tax' => 0.0],
                ['from' => 40_000.0, 'to' => 55_000.0, 'rate' => 0.10, 'taxable' => 15_000.0, 'tax' => 1_500.0],
            ],
        );
    }

    // -----------------------------------------------------------------------
    // effectiveAnnualRate()
    // -----------------------------------------------------------------------

    /** @test */
    public function effective_rate_returns_zero_when_taxable_income_is_zero(): void
    {
        $result = new EtaTaxWithholding(
            monthlyGross: 2_000.0,
            nosiEmployeeDeduction: 198.0,
            personalExemptionMonthly: 1_666.67,
            monthlyTaxableIncome: 0.0,
            annualTaxableIncome: 0.0,
            annualTax: 0.0,
            monthlyWithholding: 0.0,
            bracketBreakdown: [],
        );

        $this->assertSame(0.0, $result->effectiveAnnualRate());
    }

    /** @test */
    public function effective_rate_is_ratio_of_annual_tax_to_taxable_income(): void
    {
        // annual_tax / annual_taxable = 3065.4 / 65436 ≈ 0.0469
        $result = $this->make(65_436.0, 3_065.4, 255.45);

        $expected = round(3_065.4 / 65_436.0, 4);
        $this->assertSame($expected, $result->effectiveAnnualRate());
    }

    // -----------------------------------------------------------------------
    // toArray()
    // -----------------------------------------------------------------------

    /** @test */
    public function to_array_contains_all_required_keys(): void
    {
        $arr = $this->make(65_436.0, 3_065.4, 255.45)->toArray();

        $this->assertArrayHasKey('monthly_gross', $arr);
        $this->assertArrayHasKey('nosi_employee_deduction', $arr);
        $this->assertArrayHasKey('personal_exemption_monthly', $arr);
        $this->assertArrayHasKey('monthly_taxable_income', $arr);
        $this->assertArrayHasKey('annual_taxable_income', $arr);
        $this->assertArrayHasKey('annual_tax', $arr);
        $this->assertArrayHasKey('monthly_withholding', $arr);
        $this->assertArrayHasKey('effective_annual_rate', $arr);
        $this->assertArrayHasKey('bracket_breakdown', $arr);
    }

    /** @test */
    public function to_array_effective_rate_matches_method(): void
    {
        $result = $this->make(65_436.0, 3_065.4, 255.45);
        $arr    = $result->toArray();

        $this->assertSame($result->effectiveAnnualRate(), $arr['effective_annual_rate']);
    }

    /** @test */
    public function to_array_bracket_breakdown_is_array(): void
    {
        $arr = $this->make(65_436.0, 3_065.4, 255.45)->toArray();

        $this->assertIsArray($arr['bracket_breakdown']);
        $this->assertNotEmpty($arr['bracket_breakdown']);
    }
}
