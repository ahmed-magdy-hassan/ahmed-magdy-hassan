<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Hr\Egypt;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EgyptPayrollControllerTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // calculate  POST /api/hr/egypt/employees/{employee}/payroll/calculate
    // -----------------------------------------------------------------------

    /** @test */
    public function calculate_returns_200_with_full_egypt_payroll_structure(): void
    {
        $employee = Employee::factory()->create(['basic_salary' => 12_000.0]);

        $this->postJson("/api/hr/egypt/employees/{$employee->id}/payroll/calculate", [
            'gross_salary' => 12_000.0,
        ])->assertOk()
            ->assertJsonStructure([
                'tax_year',
                'gross_salary',
                'nosi'         => ['employee_amount', 'employer_amount', 'work_injury_amount'],
                'income_tax'   => ['monthly_gross', 'monthly_withholding', 'annual_tax'],
                'training_fund_monthly',
                'net_salary',
                'total_employer_cost',
            ]);
    }

    /** @test */
    public function calculate_uses_provided_tax_year(): void
    {
        $employee = Employee::factory()->create(['basic_salary' => 10_000.0]);

        $response = $this->postJson("/api/hr/egypt/employees/{$employee->id}/payroll/calculate", [
            'gross_salary' => 10_000.0,
            'tax_year'     => 2024,
        ])->assertOk();

        $this->assertSame(2024, $response->json('tax_year'));
    }

    /** @test */
    public function calculate_returns_422_on_negative_gross_salary(): void
    {
        $employee = Employee::factory()->create();

        $this->postJson("/api/hr/egypt/employees/{$employee->id}/payroll/calculate", [
            'gross_salary' => -100.0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrorFor('gross_salary');
    }

    /** @test */
    public function calculate_returns_422_when_gross_salary_is_missing(): void
    {
        $employee = Employee::factory()->create();

        $this->postJson("/api/hr/egypt/employees/{$employee->id}/payroll/calculate", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('gross_salary');
    }

    /** @test */
    public function calculate_net_salary_is_less_than_gross(): void
    {
        $employee = Employee::factory()->create(['basic_salary' => 20_000.0]);

        $response = $this->postJson("/api/hr/egypt/employees/{$employee->id}/payroll/calculate", [
            'gross_salary' => 20_000.0,
        ])->assertOk();

        $this->assertLessThan(20_000.0, $response->json('net_salary'));
    }

    // -----------------------------------------------------------------------
    // labourLaw14Entitlements  GET /api/hr/egypt/employees/{employee}/labour-law-14
    // -----------------------------------------------------------------------

    /** @test */
    public function labour_law_14_returns_200_with_entitlements_structure(): void
    {
        $employee = Employee::factory()->create([
            'basic_salary' => 8_000.0,
            'hire_date'    => now()->subYears(3)->toDateString(),
        ]);

        $this->getJson("/api/hr/egypt/employees/{$employee->id}/labour-law-14")
            ->assertOk()
            ->assertJsonStructure([
                'years_of_service',
                'annual_leave_days',
                'special_needs_leave_days',
                'maternity_leave_days',
                'maternity_max_times',
                'notice_period_days',
                'annual_increment_amount',
                'annual_increment_rate',
                'effective_law_date',
            ]);
    }

    /** @test */
    public function labour_law_14_returns_15_leave_days_for_under_10_years(): void
    {
        $employee = Employee::factory()->create([
            'hire_date' => now()->subYears(3)->toDateString(),
        ]);

        $response = $this->getJson("/api/hr/egypt/employees/{$employee->id}/labour-law-14")
            ->assertOk();

        $this->assertSame(15, $response->json('annual_leave_days'));
    }

    /** @test */
    public function labour_law_14_returns_21_leave_days_for_10_or_more_years(): void
    {
        $employee = Employee::factory()->create([
            'hire_date' => now()->subYears(11)->toDateString(),
        ]);

        $response = $this->getJson("/api/hr/egypt/employees/{$employee->id}/labour-law-14")
            ->assertOk();

        $this->assertSame(21, $response->json('annual_leave_days'));
    }

    // -----------------------------------------------------------------------
    // etaForm4  GET /api/hr/egypt/companies/{company}/payroll/eta-form4
    // -----------------------------------------------------------------------

    /** @test */
    public function eta_form4_returns_200_with_form_structure(): void
    {
        Employee::factory()->count(3)->create([
            'company_id' => 1,
            'eta_tax_id' => '12345678',
            'basic_salary' => 10_000.0,
        ]);

        $this->getJson('/api/hr/egypt/companies/1/payroll/eta-form4?year=2026&quarter=1')
            ->assertOk()
            ->assertJsonStructure([
                'company_id',
                'year',
                'quarter',
                'period'       => ['from', 'to'],
                'generated_at',
                'employees'    => [
                    '*' => ['eta_tax_id', 'gross_salary', 'monthly_withholdings', 'quarterly_total'],
                ],
                'summary' => ['employee_count', 'quarterly_total'],
            ]);
    }

    /** @test */
    public function eta_form4_excludes_employees_without_eta_tax_id(): void
    {
        Employee::factory()->create(['company_id' => 1, 'eta_tax_id' => null, 'basic_salary' => 10_000.0]);
        Employee::factory()->create(['company_id' => 1, 'eta_tax_id' => '99999999', 'basic_salary' => 10_000.0]);

        $response = $this->getJson('/api/hr/egypt/companies/1/payroll/eta-form4?year=2026&quarter=1')
            ->assertOk();

        $this->assertCount(1, $response->json('employees'));
    }

    /** @test */
    public function eta_form4_returns_empty_employees_when_none_are_registered(): void
    {
        $response = $this->getJson('/api/hr/egypt/companies/999/payroll/eta-form4?year=2026&quarter=1')
            ->assertOk();

        $this->assertSame([], $response->json('employees'));
        $this->assertSame(0, $response->json('summary.employee_count'));
        $this->assertSame(0.0, $response->json('summary.quarterly_total'));
    }

    /** @test */
    public function eta_form4_returns_422_on_invalid_quarter(): void
    {
        $this->getJson('/api/hr/egypt/companies/1/payroll/eta-form4?year=2026&quarter=5')
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('quarter');
    }

    /** @test */
    public function eta_form4_returns_422_on_year_before_2022(): void
    {
        $this->getJson('/api/hr/egypt/companies/1/payroll/eta-form4?year=2021&quarter=1')
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('year');
    }

    /** @test */
    public function eta_form4_period_from_and_to_are_correct_for_q1(): void
    {
        $response = $this->getJson('/api/hr/egypt/companies/1/payroll/eta-form4?year=2026&quarter=1')
            ->assertOk();

        $this->assertSame('2026-01-01', $response->json('period.from'));
        $this->assertSame('2026-03-31', $response->json('period.to'));
    }

    /** @test */
    public function eta_form4_period_from_and_to_are_correct_for_q4(): void
    {
        $response = $this->getJson('/api/hr/egypt/companies/1/payroll/eta-form4?year=2026&quarter=4')
            ->assertOk();

        $this->assertSame('2026-10-01', $response->json('period.from'));
        $this->assertSame('2026-12-31', $response->json('period.to'));
    }

    /** @test */
    public function eta_form4_monthly_withholdings_contains_three_months(): void
    {
        Employee::factory()->create([
            'company_id'   => 1,
            'eta_tax_id'   => '11112222',
            'basic_salary' => 15_000.0,
        ]);

        $response = $this->getJson('/api/hr/egypt/companies/1/payroll/eta-form4?year=2026&quarter=2')
            ->assertOk();

        $withholdings = $response->json('employees.0.monthly_withholdings');

        $this->assertCount(3, $withholdings);
        $this->assertArrayHasKey('2026-04', $withholdings);
        $this->assertArrayHasKey('2026-05', $withholdings);
        $this->assertArrayHasKey('2026-06', $withholdings);
    }

    /** @test */
    public function eta_form4_quarterly_total_equals_sum_of_monthly_withholdings(): void
    {
        Employee::factory()->create([
            'company_id'   => 1,
            'eta_tax_id'   => '33334444',
            'basic_salary' => 18_000.0,
        ]);

        $response = $this->getJson('/api/hr/egypt/companies/1/payroll/eta-form4?year=2026&quarter=1')
            ->assertOk();

        $employee        = $response->json('employees.0');
        $sumOfMonthly    = array_sum($employee['monthly_withholdings']);
        $reportedTotal   = $employee['quarterly_total'];

        $this->assertEqualsWithDelta($sumOfMonthly, $reportedTotal, 0.01);
    }
}
