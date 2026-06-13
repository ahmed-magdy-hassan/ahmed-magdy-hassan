<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll\Egypt;

use App\Services\Payroll\Egypt\EgyptPayrollConfig;
use App\Services\Payroll\Egypt\NosiContributionService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NosiContributionServiceTest extends TestCase
{
    private NosiContributionService $service;
    private EgyptPayrollConfig $config2026;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service    = new NosiContributionService();
        $this->config2026 = EgyptPayrollConfig::forYear(2026);
    }

    /** @test */
    public function mid_range_salary_uses_actual_salary_as_insured_base(): void
    {
        // EGP 8,000 is within the 2026 cap range [1,800 – 16,441]
        $result = $this->service->calculate(8_000.0, $this->config2026);

        $this->assertSame(8_000.0, $result->insuredSalary);
        $this->assertEqualsWithDelta(880.0, $result->employeeAmount, 0.01);  // 8000 × 11%
        $this->assertEqualsWithDelta(1_500.0, $result->employerBaseAmount, 0.01); // 8000 × 18.75%
        $this->assertEqualsWithDelta(80.0, $result->workInjuryAmount, 0.01);  // 8000 × 1%
    }

    /** @test */
    public function salary_below_minimum_is_clamped_to_minimum(): void
    {
        // EGP 500 < min 1,800 → insured salary = 1,800
        $result = $this->service->calculate(500.0, $this->config2026);

        $this->assertSame($this->config2026->nosiMinInsuredSalary, $result->insuredSalary);
        $this->assertEqualsWithDelta(198.0, $result->employeeAmount, 0.01); // 1800 × 11%
    }

    /** @test */
    public function salary_above_maximum_is_clamped_to_cap(): void
    {
        // EGP 50,000 >> max 14,297 → insured salary = 14,297
        $result = $this->service->calculate(50_000.0, $this->config2026);

        $this->assertSame($this->config2026->nosiMaxInsuredSalary, $result->insuredSalary);
        $this->assertSame(50_000.0, $result->grossSalary);
    }

    /** @test */
    public function total_employer_amount_sums_base_and_work_injury(): void
    {
        $result = $this->service->calculate(8_000.0, $this->config2026);

        $expected = round(8_000.0 * (EgyptPayrollConfig::NOSI_EMPLOYER_RATE + EgyptPayrollConfig::WORK_INJURY_RATE), 2);
        $this->assertEqualsWithDelta($expected, $result->totalEmployerAmount(), 0.01);
    }

    /** @test */
    public function throws_on_negative_salary(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->calculate(-100.0, $this->config2026);
    }

    /** @test */
    public function nosi_caps_increase_across_years(): void
    {
        $cap2024 = EgyptPayrollConfig::forYear(2024)->nosiMaxInsuredSalary;
        $cap2026 = EgyptPayrollConfig::forYear(2026)->nosiMaxInsuredSalary;

        // Two years of 15% compounding: 2024 × 1.15² ≈ 2026 cap
        $this->assertGreaterThan($cap2024, $cap2026);
        $this->assertEqualsWithDelta($cap2024 * (1.15 ** 2), $cap2026, $cap2024 * 0.03);
    }
}
