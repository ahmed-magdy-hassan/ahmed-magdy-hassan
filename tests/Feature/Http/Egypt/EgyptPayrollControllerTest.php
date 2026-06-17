<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Egypt;

use App\Models\Employee;
use App\Services\Payroll\Egypt\EgyptPayrollCalculator;
use App\Services\Payroll\Egypt\LabourLaw14Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature (HTTP) tests for EgyptPayrollController.
 *
 * These tests require a full Laravel test harness (RefreshDatabase, application
 * boot, in-memory SQLite) and should run in the main Laravel project alongside
 * the application code.  They are provided here as the correct test contract —
 * register them in the hriest application's tests/Feature/ directory.
 *
 * Endpoints covered:
 *   POST /api/hr/egypt/employees/{employee}/payroll/calculate
 *   GET  /api/hr/egypt/employees/{employee}/labour-law-14
 */
final class EgyptPayrollControllerTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = Employee::factory()->create([
            'basic_salary'       => 12_000.00,
            'hire_date'          => '2020-06-01',
            'nosi_insured_salary'=> null,
            'special_needs'      => false,
        ]);
    }

    // -----------------------------------------------------------------------
    // POST /api/hr/egypt/employees/{employee}/payroll/calculate
    // -----------------------------------------------------------------------

    /** @test */
    public function calculate_returns_200_with_full_payroll_result(): void
    {
        $response = $this->actingAs($this->employee->user)
            ->postJson("/api/hr/egypt/employees/{$this->employee->id}/payroll/calculate", [
                'gross_salary' => 12_000.00,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tax_year',
                'gross_salary',
                'nosi' => ['gross_salary', 'insured_salary', 'employee_amount', 'rates'],
                'income_tax' => ['monthly_gross', 'monthly_withholding', 'bracket_breakdown'],
                'training_fund_monthly',
                'net_salary',
                'total_employer_cost',
            ]);
    }

    /** @test */
    public function calculate_uses_employee_default_salary_when_not_supplied(): void
    {
        $response = $this->actingAs($this->employee->user)
            ->postJson("/api/hr/egypt/employees/{$this->employee->id}/payroll/calculate", []);

        // basic_salary = 12,000; controller falls back to employee record
        $response->assertStatus(200)
            ->assertJsonPath('gross_salary', 12_000.0);
    }

    /** @test */
    public function calculate_returns_422_on_negative_gross_salary(): void
    {
        $this->actingAs($this->employee->user)
            ->postJson("/api/hr/egypt/employees/{$this->employee->id}/payroll/calculate", [
                'gross_salary' => -500,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('gross_salary');
    }

    /** @test */
    public function calculate_accepts_custom_tax_year(): void
    {
        $this->actingAs($this->employee->user)
            ->postJson("/api/hr/egypt/employees/{$this->employee->id}/payroll/calculate", [
                'gross_salary' => 12_000.00,
                'tax_year'     => 2026,
            ])
            ->assertStatus(200)
            ->assertJsonPath('tax_year', 2026);
    }

    /** @test */
    public function calculate_returns_422_for_out_of_range_tax_year(): void
    {
        $this->actingAs($this->employee->user)
            ->postJson("/api/hr/egypt/employees/{$this->employee->id}/payroll/calculate", [
                'gross_salary' => 10_000,
                'tax_year'     => 2000,  // below min:2022
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('tax_year');
    }

    /** @test */
    public function calculate_returns_404_for_unknown_employee(): void
    {
        $this->actingAs($this->employee->user)
            ->postJson('/api/hr/egypt/employees/99999/payroll/calculate', [
                'gross_salary' => 10_000,
            ])
            ->assertStatus(404);
    }

    /** @test */
    public function calculate_net_salary_is_less_than_gross(): void
    {
        $response = $this->actingAs($this->employee->user)
            ->postJson("/api/hr/egypt/employees/{$this->employee->id}/payroll/calculate", [
                'gross_salary' => 12_000.00,
            ]);

        $data = $response->json();
        $this->assertLessThan($data['gross_salary'], $data['net_salary']);
    }

    /** @test */
    public function calculate_returns_401_when_unauthenticated(): void
    {
        $this->postJson("/api/hr/egypt/employees/{$this->employee->id}/payroll/calculate", [
            'gross_salary' => 10_000,
        ])->assertStatus(401);
    }

    // -----------------------------------------------------------------------
    // GET /api/hr/egypt/employees/{employee}/labour-law-14
    // -----------------------------------------------------------------------

    /** @test */
    public function labour_law_14_returns_200_with_correct_structure(): void
    {
        $this->actingAs($this->employee->user)
            ->getJson("/api/hr/egypt/employees/{$this->employee->id}/labour-law-14")
            ->assertStatus(200)
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
    public function labour_law_14_maternity_days_is_120(): void
    {
        $this->actingAs($this->employee->user)
            ->getJson("/api/hr/egypt/employees/{$this->employee->id}/labour-law-14")
            ->assertStatus(200)
            ->assertJsonPath('maternity_leave_days', 120)
            ->assertJsonPath('maternity_max_times', 3);
    }

    /** @test */
    public function labour_law_14_effective_date_is_2025_09_01(): void
    {
        $this->actingAs($this->employee->user)
            ->getJson("/api/hr/egypt/employees/{$this->employee->id}/labour-law-14")
            ->assertJsonPath('effective_law_date', LabourLaw14Service::EFFECTIVE_DATE);
    }

    /** @test */
    public function labour_law_14_returns_401_when_unauthenticated(): void
    {
        $this->getJson("/api/hr/egypt/employees/{$this->employee->id}/labour-law-14")
            ->assertStatus(401);
    }
}
