<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll\Egypt;

use App\Services\Payroll\Egypt\EgyptPayrollConfig;
use App\Services\Payroll\Egypt\LabourLaw14Service;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LabourLaw14ServiceTest extends TestCase
{
    private LabourLaw14Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LabourLaw14Service();
    }

    // -----------------------------------------------------------------------
    // Annual increment
    // -----------------------------------------------------------------------

    /** @test */
    public function annual_increment_is_3_percent_of_insured_salary(): void
    {
        $this->assertEqualsWithDelta(540.0, $this->service->annualIncrementAmount(18_000.0), 0.01);
        $this->assertEqualsWithDelta(54.0,  $this->service->annualIncrementAmount(1_800.0), 0.01);
    }

    /** @test */
    public function annual_increment_throws_on_negative_salary(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->annualIncrementAmount(-100.0);
    }

    // -----------------------------------------------------------------------
    // Training fund
    // -----------------------------------------------------------------------

    /** @test */
    public function training_fund_is_zero_below_30_employees(): void
    {
        $this->assertSame(0.0, $this->service->trainingFundMonthly(1_800.0, 29));
        $this->assertSame(0.0, $this->service->trainingFundMonthly(1_800.0, 1));
    }

    /** @test */
    public function training_fund_calculated_for_30_or_more_employees(): void
    {
        // 0.25% of min insured salary (1800) = EGP 4.50
        $expected = round(1_800.0 * EgyptPayrollConfig::TRAINING_FUND_RATE, 2);
        $this->assertEqualsWithDelta($expected, $this->service->trainingFundMonthly(1_800.0, 30), 0.01);
        $this->assertEqualsWithDelta($expected, $this->service->trainingFundMonthly(1_800.0, 100), 0.01);
    }

    /** @test */
    public function training_fund_total_multiplies_per_employee_cost_by_headcount(): void
    {
        $perEmployee = $this->service->trainingFundMonthly(1_800.0, 50);
        $total       = $this->service->trainingFundTotal(1_800.0, 50);

        $this->assertEqualsWithDelta($perEmployee * 50, $total, 0.01);
    }

    // -----------------------------------------------------------------------
    // Leave entitlements
    // -----------------------------------------------------------------------

    /** @test */
    public function year_one_employee_gets_15_days(): void
    {
        $this->assertSame(15, $this->service->leaveEntitlementDays(0));
        $this->assertSame(15, $this->service->leaveEntitlementDays(1));
    }

    /** @test */
    public function year_two_and_beyond_gets_21_days(): void
    {
        $this->assertSame(21, $this->service->leaveEntitlementDays(2));
        $this->assertSame(21, $this->service->leaveEntitlementDays(10));
    }

    /** @test */
    public function special_needs_always_gets_45_days_regardless_of_tenure(): void
    {
        $this->assertSame(45, $this->service->leaveEntitlementDays(0, specialNeeds: true));
        $this->assertSame(45, $this->service->leaveEntitlementDays(15, specialNeeds: true));
    }

    // -----------------------------------------------------------------------
    // Maternity leave
    // -----------------------------------------------------------------------

    /** @test */
    public function maternity_leave_is_120_days(): void
    {
        $this->assertSame(120, $this->service->maternityLeaveDays());
        $this->assertSame(3,   $this->service->maternityLeaveMaxOccurrences());
    }

    // -----------------------------------------------------------------------
    // Notice period
    // -----------------------------------------------------------------------

    /** @test */
    public function notice_period_is_60_days_below_10_years(): void
    {
        $this->assertSame(60, $this->service->noticePeriodDays(0));
        $this->assertSame(60, $this->service->noticePeriodDays(9));
    }

    /** @test */
    public function notice_period_is_90_days_at_10_or_more_years(): void
    {
        $this->assertSame(90, $this->service->noticePeriodDays(10));
        $this->assertSame(90, $this->service->noticePeriodDays(20));
    }

    // -----------------------------------------------------------------------
    // Severance
    // -----------------------------------------------------------------------

    /** @test */
    public function employer_termination_severance_is_2_months_per_year(): void
    {
        $this->assertSame(10.0, $this->service->severanceMonths(5, isEmployerTermination: true));
        $this->assertSame(20.0, $this->service->severanceMonths(10, isEmployerTermination: true));
    }

    /** @test */
    public function resignation_severance_is_zero_below_5_years(): void
    {
        $this->assertSame(0.0, $this->service->severanceMonths(4, isEmployerTermination: false));
        $this->assertSame(0.0, $this->service->severanceMonths(0, isEmployerTermination: false));
    }

    /** @test */
    public function resignation_severance_is_1_month_per_year_from_5_years(): void
    {
        $this->assertSame(5.0,  $this->service->severanceMonths(5,  isEmployerTermination: false));
        $this->assertSame(10.0, $this->service->severanceMonths(10, isEmployerTermination: false));
    }
}
