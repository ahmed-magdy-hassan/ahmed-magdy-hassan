<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll\Saudi;

use App\Enums\Payroll\GosiNationality;
use App\Enums\Payroll\GosiScheme;
use App\Services\Payroll\Saudi\GosiConfig;
use App\Services\Payroll\Saudi\GosiContributionService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GosiContributionServiceTest extends TestCase
{
    private GosiContributionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GosiContributionService();
    }

    /** @test */
    public function saudi_old_scheme_employee_pays_10_percent(): void
    {
        $result = $this->service->calculate(10_000.0, GosiNationality::SaudiNational, GosiScheme::Old);

        $this->assertSame(1_000.0, $result->employeeAnnuityAmount);
        $this->assertSame(0.10, $result->employeeAnnuityRate);
    }

    /** @test */
    public function saudi_old_scheme_employer_pays_12_percent(): void
    {
        // 10 % annuity + 2 % occupational
        $result = $this->service->calculate(10_000.0, GosiNationality::SaudiNational, GosiScheme::Old);

        $this->assertSame(1_000.0, $result->employerAnnuityAmount);
        $this->assertSame(200.0,   $result->employerOccupationalAmount);
        $this->assertSame(1_200.0, $result->totalEmployerCost());
    }

    /** @test */
    public function saudi_new_scheme_employee_pays_9_percent(): void
    {
        $result = $this->service->calculate(10_000.0, GosiNationality::SaudiNational, GosiScheme::New);

        $this->assertSame(900.0, $result->employeeAnnuityAmount);
        $this->assertSame(0.09,  $result->employeeAnnuityRate);
    }

    /** @test */
    public function saudi_new_scheme_employer_pays_11_percent(): void
    {
        // 9 % annuity + 2 % occupational
        $result = $this->service->calculate(10_000.0, GosiNationality::SaudiNational, GosiScheme::New);

        $this->assertSame(900.0,   $result->employerAnnuityAmount);
        $this->assertSame(200.0,   $result->employerOccupationalAmount);
        $this->assertSame(1_100.0, $result->totalEmployerCost());
    }

    /** @test */
    public function expatriate_has_zero_employee_deduction(): void
    {
        $result = $this->service->calculate(15_000.0, GosiNationality::Expatriate);

        $this->assertSame(0.0, $result->employeeAnnuityAmount);
        $this->assertSame(0.0, $result->employeeDeduction());
    }

    /** @test */
    public function expatriate_employer_pays_2_percent_occupational_only(): void
    {
        // 15,000 × 2 % = 300
        $result = $this->service->calculate(15_000.0, GosiNationality::Expatriate);

        $this->assertSame(0.0,   $result->employerAnnuityAmount);
        $this->assertSame(300.0, $result->employerOccupationalAmount);
        $this->assertSame(300.0, $result->totalEmployerCost());
    }

    /** @test */
    public function salary_above_ceiling_is_capped_at_45000(): void
    {
        $result = $this->service->calculate(60_000.0, GosiNationality::SaudiNational, GosiScheme::Old);

        $this->assertSame(GosiConfig::WAGE_CEILING, $result->insuredSalary);
        $this->assertSame(4_500.0, $result->employeeAnnuityAmount);  // 45,000 × 10%
    }

    /** @test */
    public function gross_salary_above_ceiling_preserved_in_value_object(): void
    {
        $result = $this->service->calculate(60_000.0, GosiNationality::SaudiNational, GosiScheme::Old);

        $this->assertSame(60_000.0, $result->grossSalary);
        $this->assertSame(45_000.0, $result->insuredSalary);
    }

    /** @test */
    public function zero_salary_yields_zero_contributions(): void
    {
        $result = $this->service->calculate(0.0, GosiNationality::SaudiNational, GosiScheme::Old);

        $this->assertSame(0.0, $result->employeeDeduction());
        $this->assertSame(0.0, $result->totalEmployerCost());
        $this->assertSame(0.0, $result->totalContribution());
    }

    /** @test */
    public function negative_salary_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->calculate(-1_000.0, GosiNationality::SaudiNational);
    }

    /** @test */
    public function default_scheme_is_old_when_omitted(): void
    {
        $result = $this->service->calculate(10_000.0, GosiNationality::SaudiNational);

        $this->assertSame(GosiScheme::Old, $result->scheme);
        $this->assertSame(1_000.0, $result->employeeAnnuityAmount);
    }

    /** @test */
    public function old_scheme_total_contribution_is_22_percent(): void
    {
        // Employee 10 % + Employer 12 % = 22 %  on 10,000 = 2,200
        $result = $this->service->calculate(10_000.0, GosiNationality::SaudiNational, GosiScheme::Old);

        $this->assertSame(2_200.0, $result->totalContribution());
    }

    /** @test */
    public function new_scheme_total_contribution_is_20_percent(): void
    {
        // Employee 9 % + Employer 11 % = 20 %  on 10,000 = 2,000
        $result = $this->service->calculate(10_000.0, GosiNationality::SaudiNational, GosiScheme::New);

        $this->assertSame(2_000.0, $result->totalContribution());
    }
}
