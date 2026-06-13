<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll\Saudi;

use App\Services\Payroll\Saudi\GosiConfig;
use PHPUnit\Framework\TestCase;

final class GosiConfigTest extends TestCase
{
    /** @test */
    public function saudi_old_scheme_rates_sum_to_22_percent(): void
    {
        // Employee 10 % + Employer 10 % annuity + 2 % occupational = 22 %
        $total = GosiConfig::SAUDI_OLD_EMPLOYEE_ANNUITY_RATE
               + GosiConfig::SAUDI_OLD_EMPLOYER_ANNUITY_RATE
               + GosiConfig::OCCUPATIONAL_HAZARD_RATE;

        $this->assertEqualsWithDelta(0.22, $total, 0.0001);
    }

    /** @test */
    public function saudi_new_scheme_rates_sum_to_20_percent(): void
    {
        // Employee 9 % + Employer 9 % annuity + 2 % occupational = 20 %
        $total = GosiConfig::SAUDI_NEW_EMPLOYEE_ANNUITY_RATE
               + GosiConfig::SAUDI_NEW_EMPLOYER_ANNUITY_RATE
               + GosiConfig::OCCUPATIONAL_HAZARD_RATE;

        $this->assertEqualsWithDelta(0.20, $total, 0.0001);
    }

    /** @test */
    public function new_scheme_employee_rate_lower_than_old(): void
    {
        $this->assertLessThan(
            GosiConfig::SAUDI_OLD_EMPLOYEE_ANNUITY_RATE,
            GosiConfig::SAUDI_NEW_EMPLOYEE_ANNUITY_RATE,
        );
    }

    /** @test */
    public function wage_ceiling_is_45000_sar(): void
    {
        $this->assertSame(45_000.0, GosiConfig::WAGE_CEILING);
    }

    /** @test */
    public function salary_change_report_window_is_15_days(): void
    {
        $this->assertSame(15, GosiConfig::SALARY_CHANGE_REPORT_DAYS);
    }

    /** @test */
    public function new_scheme_effective_date_is_july_2025(): void
    {
        $this->assertSame('2025-07-01', GosiConfig::NEW_SCHEME_EFFECTIVE_DATE);
    }

    /** @test */
    public function occupational_hazard_rate_is_2_percent(): void
    {
        $this->assertSame(0.02, GosiConfig::OCCUPATIONAL_HAZARD_RATE);
    }
}
