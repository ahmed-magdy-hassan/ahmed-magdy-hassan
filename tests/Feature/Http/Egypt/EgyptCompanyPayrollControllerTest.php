<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Egypt;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature (HTTP) tests for EgyptCompanyPayrollController.
 *
 * Requires the hriest Laravel application with:
 *   - Company model with employees() relationship
 *   - Employee model with egypt fields (national_id, nosi_number, nosi_insured_salary, status)
 *   - egypt.php routes registered in routes/api.php
 *   - EgyptCompanyPayrollController bound in AppServiceProvider
 *
 * Register in: hriest/tests/Feature/Http/Egypt/
 *
 * Endpoints:
 *   POST /api/hr/egypt/companies/{company}/payroll/run
 *   GET  /api/hr/egypt/companies/{company}/payroll/eta-form4
 *   GET  /api/hr/egypt/companies/{company}/payroll/nosi-report
 */
final class EgyptCompanyPayrollControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company  $company;
    private Employee $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company   = Company::factory()->create(['headcount' => 5]);
        $this->adminUser = Employee::factory()->admin()->create(['company_id' => $this->company->id]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────

    private function employeePayload(string $id = 'EMP-001', float $gross = 10_000.0): array
    {
        return [
            'employee_id' => $id,
            'name'        => 'Ahmed Ali',
            'national_id' => '29001011234567',
            'nosi_number' => '1234567890',
            'gross_salary'=> $gross,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // POST /api/hr/egypt/companies/{company}/payroll/run
    // ──────────────────────────────────────────────────────────────────────

    /** @test */
    public function run_returns_201_with_payroll_result(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson("/api/hr/egypt/companies/{$this->company->id}/payroll/run", [
                'year'      => 2026,
                'month'     => 6,
                'employees' => [$this->employeePayload()],
            ])
            ->assertStatus(201)
            ->assertJsonStructure([
                'year', 'month', 'tax_year', 'employee_count',
                'totals' => [
                    'gross_salary', 'net_salary', 'nosi_employee',
                    'nosi_employer', 'tax_withheld', 'training_fund', 'employer_cost',
                ],
                'employees',
            ]);
    }

    /** @test */
    public function run_employee_count_matches_input(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson("/api/hr/egypt/companies/{$this->company->id}/payroll/run", [
                'year'      => 2026,
                'month'     => 6,
                'employees' => [
                    $this->employeePayload('A', 8_000.0),
                    $this->employeePayload('B', 12_000.0),
                    $this->employeePayload('C', 6_000.0),
                ],
            ])
            ->assertStatus(201)
            ->assertJsonPath('employee_count', 3);
    }

    /** @test */
    public function run_returns_422_when_employees_missing(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson("/api/hr/egypt/companies/{$this->company->id}/payroll/run", [
                'year'  => 2026,
                'month' => 6,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('employees');
    }

    /** @test */
    public function run_returns_422_on_invalid_national_id_length(): void
    {
        $payload = $this->employeePayload();
        $payload['national_id'] = '123';  // must be exactly 14 digits

        $this->actingAs($this->adminUser)
            ->postJson("/api/hr/egypt/companies/{$this->company->id}/payroll/run", [
                'year'      => 2026,
                'month'     => 6,
                'employees' => [$payload],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('employees.0.national_id');
    }

    /** @test */
    public function run_returns_422_on_negative_gross_salary(): void
    {
        $this->actingAs($this->adminUser)
            ->postJson("/api/hr/egypt/companies/{$this->company->id}/payroll/run", [
                'year'      => 2026,
                'month'     => 6,
                'employees' => [$this->employeePayload('X', -500.0)],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('employees.0.gross_salary');
    }

    /** @test */
    public function run_net_salary_is_less_than_gross(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/hr/egypt/companies/{$this->company->id}/payroll/run", [
                'year'      => 2026,
                'month'     => 6,
                'employees' => [$this->employeePayload()],
            ]);

        $totals = $response->json('totals');
        $this->assertLessThan($totals['gross_salary'], $totals['net_salary']);
    }

    /** @test */
    public function run_returns_401_when_unauthenticated(): void
    {
        $this->postJson("/api/hr/egypt/companies/{$this->company->id}/payroll/run", [
            'year' => 2026, 'month' => 6, 'employees' => [],
        ])->assertStatus(401);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /api/hr/egypt/companies/{company}/payroll/eta-form4
    // ──────────────────────────────────────────────────────────────────────

    /** @test */
    public function eta_form4_returns_200_with_correct_structure(): void
    {
        Employee::factory()->count(3)->create([
            'company_id'  => $this->company->id,
            'status'      => 'active',
            'national_id' => '29001011234567',
            'nosi_number' => '1234567890',
            'basic_salary'=> 10_000.0,
        ]);

        $this->actingAs($this->adminUser)
            ->getJson("/api/hr/egypt/companies/{$this->company->id}/payroll/eta-form4?year=2026&quarter=2")
            ->assertStatus(200)
            ->assertJsonStructure([
                'year', 'quarter', 'period_label', 'months', 'due_date',
                'total_employee_count', 'total_quarterly_gross',
                'total_quarterly_tax_withheld', 'employees',
            ]);
    }

    /** @test */
    public function eta_form4_due_date_for_q2_is_july_31(): void
    {
        Employee::factory()->create([
            'company_id'  => $this->company->id,
            'status'      => 'active',
            'national_id' => '29001011234567',
            'nosi_number' => '1234567890',
            'basic_salary'=> 8_000.0,
        ]);

        $this->actingAs($this->adminUser)
            ->getJson("/api/hr/egypt/companies/{$this->company->id}/payroll/eta-form4?year=2026&quarter=2")
            ->assertJsonPath('due_date', '2026-07-31');
    }

    /** @test */
    public function eta_form4_returns_422_on_invalid_quarter(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson("/api/hr/egypt/companies/{$this->company->id}/payroll/eta-form4?year=2026&quarter=5")
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('quarter');
    }

    /** @test */
    public function eta_form4_returns_422_when_year_missing(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson("/api/hr/egypt/companies/{$this->company->id}/payroll/eta-form4?quarter=2")
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('year');
    }

    /** @test */
    public function eta_form4_returns_empty_employees_when_no_active_staff(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson("/api/hr/egypt/companies/{$this->company->id}/payroll/eta-form4?year=2026&quarter=1")
            ->assertStatus(200)
            ->assertJsonPath('employees', []);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GET /api/hr/egypt/companies/{company}/payroll/nosi-report
    // ──────────────────────────────────────────────────────────────────────

    /** @test */
    public function nosi_report_returns_200_with_correct_structure(): void
    {
        Employee::factory()->create([
            'company_id'  => $this->company->id,
            'status'      => 'active',
            'nosi_number' => '1234567890',
            'basic_salary'=> 8_000.0,
        ]);

        $this->actingAs($this->adminUser)
            ->getJson("/api/hr/egypt/companies/{$this->company->id}/payroll/nosi-report?year=2026&month=6")
            ->assertStatus(200)
            ->assertJsonStructure([
                'year', 'month', 'period_label', 'due_date', 'employee_count',
                'total_gross_salary', 'total_insured_salary',
                'total_employee_contribution', 'total_employer_base_contribution',
                'total_work_injury_contribution', 'total_employer_contribution',
                'grand_total_contribution', 'employees',
            ]);
    }

    /** @test */
    public function nosi_report_due_date_is_15th_of_following_month(): void
    {
        Employee::factory()->create([
            'company_id'  => $this->company->id,
            'status'      => 'active',
            'nosi_number' => '1234567890',
            'basic_salary'=> 8_000.0,
        ]);

        $this->actingAs($this->adminUser)
            ->getJson("/api/hr/egypt/companies/{$this->company->id}/payroll/nosi-report?year=2026&month=6")
            ->assertJsonPath('due_date', '2026-07-15');
    }

    /** @test */
    public function nosi_report_returns_422_on_invalid_month(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson("/api/hr/egypt/companies/{$this->company->id}/payroll/nosi-report?year=2026&month=13")
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('month');
    }

    /** @test */
    public function nosi_report_returns_422_when_month_missing(): void
    {
        $this->actingAs($this->adminUser)
            ->getJson("/api/hr/egypt/companies/{$this->company->id}/payroll/nosi-report?year=2026")
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('month');
    }

    /** @test */
    public function nosi_report_returns_401_when_unauthenticated(): void
    {
        $this->getJson("/api/hr/egypt/companies/{$this->company->id}/payroll/nosi-report?year=2026&month=6")
            ->assertStatus(401);
    }
}
