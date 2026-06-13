<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects\Egypt;

use App\ValueObjects\Egypt\NosiContribution;
use PHPUnit\Framework\TestCase;

final class NosiContributionTest extends TestCase
{
    private NosiContribution $subject;

    protected function setUp(): void
    {
        parent::setUp();
        // Employee: 8000 × 11% = 880, employer: 8000 × 18.75% = 1500, work-injury: 8000 × 1% = 80
        $this->subject = new NosiContribution(
            grossSalary: 8_000.0,
            insuredSalary: 8_000.0,
            employeeAmount: 880.0,
            employerBaseAmount: 1_500.0,
            workInjuryAmount: 80.0,
            employeeRate: 0.11,
            employerRate: 0.1875,
            workInjuryRate: 0.01,
        );
    }

    /** @test */
    public function total_employer_amount_sums_base_and_work_injury(): void
    {
        $this->assertSame(1_580.0, $this->subject->totalEmployerAmount());
    }

    /** @test */
    public function total_contribution_sums_employee_and_employer(): void
    {
        // 880 + 1580 = 2460
        $this->assertSame(2_460.0, $this->subject->totalContribution());
    }

    /** @test */
    public function to_array_contains_all_required_keys(): void
    {
        $arr = $this->subject->toArray();

        $this->assertArrayHasKey('gross_salary', $arr);
        $this->assertArrayHasKey('insured_salary', $arr);
        $this->assertArrayHasKey('employee_amount', $arr);
        $this->assertArrayHasKey('employer_base_amount', $arr);
        $this->assertArrayHasKey('work_injury_amount', $arr);
        $this->assertArrayHasKey('total_employer', $arr);
        $this->assertArrayHasKey('total_contribution', $arr);
        $this->assertArrayHasKey('rates', $arr);
    }

    /** @test */
    public function to_array_rates_subarray_has_correct_keys_and_values(): void
    {
        $rates = $this->subject->toArray()['rates'];

        $this->assertArrayHasKey('employee', $rates);
        $this->assertArrayHasKey('employer', $rates);
        $this->assertArrayHasKey('work_injury', $rates);

        $this->assertSame(0.11,   $rates['employee']);
        $this->assertSame(0.1875, $rates['employer']);
        $this->assertSame(0.01,   $rates['work_injury']);
    }

    /** @test */
    public function to_array_computed_totals_match_methods(): void
    {
        $arr = $this->subject->toArray();

        $this->assertSame($this->subject->totalEmployerAmount(), $arr['total_employer']);
        $this->assertSame($this->subject->totalContribution(),   $arr['total_contribution']);
    }

    /** @test */
    public function gross_salary_differs_from_insured_salary_when_capped(): void
    {
        $capped = new NosiContribution(
            grossSalary: 50_000.0,
            insuredSalary: 16_441.0,
            employeeAmount: 1_808.51,
            employerBaseAmount: 3_082.69,
            workInjuryAmount: 164.41,
            employeeRate: 0.11,
            employerRate: 0.1875,
            workInjuryRate: 0.01,
        );

        $this->assertSame(50_000.0, $capped->grossSalary);
        $this->assertSame(16_441.0, $capped->insuredSalary);
    }
}
