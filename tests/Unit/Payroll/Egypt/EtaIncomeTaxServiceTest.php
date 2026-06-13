<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll\Egypt;

use App\Services\Payroll\Egypt\EgyptPayrollConfig;
use App\Services\Payroll\Egypt\EtaIncomeTaxService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EtaIncomeTaxServiceTest extends TestCase
{
    private EtaIncomeTaxService $service;
    private EgyptPayrollConfig  $config2025;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service    = new EtaIncomeTaxService();
        $this->config2025 = EgyptPayrollConfig::forYear(2025);
    }

    /** @test */
    public function very_low_income_falls_entirely_in_zero_bracket(): void
    {
        // Monthly gross: EGP 2,000
        // Monthly taxable ≈ 2000 - 198 (NOSI 11% of 1800 clamped) - 1667 (exemption) < 0 → 0
        // Actual: 2000 - nosiEmployee - 1667 → depends on NOSI; result should be EGP 0 tax
        $nosi   = 220.0;  // approximate NOSI for 2000 EGP gross
        $result = $this->service->calculate(2_000.0, $nosi, $this->config2025);

        $this->assertSame(0.0, $result->monthlyWithholding);
        $this->assertSame(0.0, $result->annualTax);
    }

    /** @test */
    public function mid_salary_produces_correct_monthly_withholding(): void
    {
        // EGP 8,000/month gross, NOSI employee = 880 (11% of 8000)
        // Monthly taxable = 8000 - 880 - 1667 = 5453
        // Annual taxable  = 5453 × 12 = 65,436
        // Tax: 0–40000: 0; 40001–55000: 1500; 55001–65436: 10436 × 15% = 1565.40
        // Annual tax ≈ 3065.40  →  monthly ≈ 255.45
        $result = $this->service->calculate(8_000.0, 880.0, $this->config2025);

        $this->assertEqualsWithDelta(5_453.0, $result->monthlyTaxableIncome, 1.0);
        $this->assertEqualsWithDelta(65_436.0, $result->annualTaxableIncome, 12.0);
        $this->assertEqualsWithDelta(3_065.40, $result->annualTax, 5.0);
        $this->assertEqualsWithDelta(255.45, $result->monthlyWithholding, 1.0);
    }

    /** @test */
    public function high_salary_reaches_top_bracket(): void
    {
        // EGP 40,000/month — NOSI capped at max, should reach 27.5% top bracket
        $nosiCapped = round($this->config2025->nosiMaxInsuredSalary * 0.11, 2);
        $result = $this->service->calculate(40_000.0, $nosiCapped, $this->config2025);

        $this->assertGreaterThan(200_000.0, $result->annualTaxableIncome);
        $this->assertGreaterThan(0.0, $result->monthlyWithholding);

        // At least one bracket at 27.5%
        $rates = array_column($result->bracketBreakdown, 'rate');
        $this->assertContains(0.275, $rates);
    }

    /** @test */
    public function bracket_breakdown_is_present_for_taxable_income(): void
    {
        $result = $this->service->calculate(8_000.0, 880.0, $this->config2025);

        $this->assertNotEmpty($result->bracketBreakdown);
        foreach ($result->bracketBreakdown as $bracket) {
            $this->assertArrayHasKey('from', $bracket);
            $this->assertArrayHasKey('rate', $bracket);
            $this->assertArrayHasKey('taxable', $bracket);
            $this->assertArrayHasKey('tax', $bracket);
        }
    }

    /** @test */
    public function effective_rate_increases_with_income(): void
    {
        $low  = $this->service->calculate(5_000.0,  550.0, $this->config2025);
        $high = $this->service->calculate(30_000.0, 1_375.0, $this->config2025);

        $this->assertGreaterThan($low->effectiveAnnualRate(), $high->effectiveAnnualRate());
    }

    /** @test */
    public function throws_on_negative_gross(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->calculate(-1_000.0, 0.0, $this->config2025);
    }
}
