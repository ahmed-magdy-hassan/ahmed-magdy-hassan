<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects\Saudi;

use App\Enums\Payroll\GosiNationality;
use App\Enums\Payroll\GosiScheme;
use App\ValueObjects\Saudi\GosiContribution;
use PHPUnit\Framework\TestCase;

final class GosiContributionTest extends TestCase
{
    private function saudiOld(float $gross = 10_000.0): GosiContribution
    {
        return new GosiContribution(
            grossSalary: $gross,
            insuredSalary: $gross,
            nationality: GosiNationality::SaudiNational,
            scheme: GosiScheme::Old,
            employeeAnnuityAmount: $gross * 0.10,
            employerAnnuityAmount: $gross * 0.10,
            employerOccupationalAmount: $gross * 0.02,
            employeeAnnuityRate: 0.10,
            employerAnnuityRate: 0.10,
            occupationalRate: 0.02,
        );
    }

    private function expatriate(float $gross = 10_000.0): GosiContribution
    {
        return new GosiContribution(
            grossSalary: $gross,
            insuredSalary: $gross,
            nationality: GosiNationality::Expatriate,
            scheme: GosiScheme::Old,
            employeeAnnuityAmount: 0.0,
            employerAnnuityAmount: 0.0,
            employerOccupationalAmount: $gross * 0.02,
            employeeAnnuityRate: 0.0,
            employerAnnuityRate: 0.0,
            occupationalRate: 0.02,
        );
    }

    /** @test */
    public function employee_deduction_equals_annuity_amount(): void
    {
        $this->assertSame(1_000.0, $this->saudiOld()->employeeDeduction());
    }

    /** @test */
    public function total_employer_cost_sums_annuity_and_occupational(): void
    {
        // 10 % + 2 % = 1,200 on 10,000
        $this->assertSame(1_200.0, $this->saudiOld()->totalEmployerCost());
    }

    /** @test */
    public function total_contribution_sums_employee_and_employer(): void
    {
        // 1,000 + 1,200 = 2,200
        $this->assertSame(2_200.0, $this->saudiOld()->totalContribution());
    }

    /** @test */
    public function expatriate_employee_deduction_is_zero(): void
    {
        $this->assertSame(0.0, $this->expatriate()->employeeDeduction());
    }

    /** @test */
    public function expatriate_employer_cost_is_occupational_only(): void
    {
        // 2 % of 10,000 = 200
        $this->assertSame(200.0, $this->expatriate()->totalEmployerCost());
    }

    /** @test */
    public function to_array_contains_all_keys(): void
    {
        $arr = $this->saudiOld()->toArray();

        foreach (['gross_salary', 'insured_salary', 'nationality', 'nationality_label',
                  'scheme', 'scheme_label', 'employee_annuity_amount', 'employer_annuity_amount',
                  'employer_occupational_amount', 'employee_deduction', 'total_employer_cost',
                  'total_contribution', 'rates'] as $key) {
            $this->assertArrayHasKey($key, $arr, "Missing key: {$key}");
        }
    }

    /** @test */
    public function to_array_rates_subarray_has_correct_keys(): void
    {
        $rates = $this->saudiOld()->toArray()['rates'];

        $this->assertArrayHasKey('employee_annuity', $rates);
        $this->assertArrayHasKey('employer_annuity', $rates);
        $this->assertArrayHasKey('occupational', $rates);
    }

    /** @test */
    public function to_array_nationality_label_is_string(): void
    {
        $this->assertIsString($this->saudiOld()->toArray()['nationality_label']);
        $this->assertIsString($this->expatriate()->toArray()['nationality_label']);
    }
}
