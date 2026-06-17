<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Hr;

use App\Enums\Payroll\TerminationReason;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EosbControllerTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // preview  GET /api/hr/employees/{employee}/eosb/preview
    // -----------------------------------------------------------------------

    /** @test */
    public function preview_returns_200_with_full_eosb_structure(): void
    {
        $employee = Employee::factory()->create([
            'basic_salary' => 10_000.0,
            'hire_date'    => '2020-01-01',
        ]);

        $response = $this->getJson("/api/hr/employees/{$employee->id}/eosb/preview?" . http_build_query([
            'end_date'           => now()->toDateString(),
            'termination_reason' => TerminationReason::EmployerTermination->value,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'monthly_wage',
                'service_years',
                'termination_reason',
                'termination_reason_label',
                'full_entitlement',
                'resignation_multiplier',
                'final_amount',
                'breakdown',
                'dates' => [
                    'hire_date' => ['gregorian', 'hijri', 'hijri_ar', 'hijri_en'],
                    'end_date'  => ['gregorian', 'hijri', 'hijri_ar', 'hijri_en'],
                ],
            ]);
    }

    /** @test */
    public function preview_returns_422_when_termination_reason_is_missing(): void
    {
        $employee = Employee::factory()->create();

        $this->getJson("/api/hr/employees/{$employee->id}/eosb/preview?" . http_build_query([
            'end_date' => now()->toDateString(),
        ]))->assertUnprocessable()
            ->assertJsonValidationErrorFor('termination_reason');
    }

    /** @test */
    public function preview_returns_422_when_neither_end_date_provided(): void
    {
        $employee = Employee::factory()->create();

        $this->getJson("/api/hr/employees/{$employee->id}/eosb/preview?" . http_build_query([
            'termination_reason' => TerminationReason::Resignation->value,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date', 'end_date_hijri']);
    }

    /** @test */
    public function preview_accepts_hijri_end_date(): void
    {
        $employee = Employee::factory()->create([
            'basic_salary' => 8_000.0,
            'hire_date'    => '2018-01-01',
        ]);

        $response = $this->getJson("/api/hr/employees/{$employee->id}/eosb/preview?" . http_build_query([
            'end_date_hijri'     => '1447-01-01',
            'termination_reason' => TerminationReason::MutualConsent->value,
        ]));

        $response->assertOk()
            ->assertJsonPath('termination_reason', TerminationReason::MutualConsent->value);
    }

    /** @test */
    public function preview_does_not_persist_eosb_to_employee(): void
    {
        $employee = Employee::factory()->create([
            'basic_salary'     => 12_000.0,
            'hire_date'        => '2019-01-01',
            'eosb_amount'      => null,
            'eosb_calculated_at' => null,
        ]);

        $this->getJson("/api/hr/employees/{$employee->id}/eosb/preview?" . http_build_query([
            'end_date'           => now()->toDateString(),
            'termination_reason' => TerminationReason::Resignation->value,
        ]))->assertOk();

        $this->assertNull($employee->fresh()->eosb_amount);
    }

    // -----------------------------------------------------------------------
    // finalize  POST /api/hr/employees/{employee}/eosb/finalize
    // -----------------------------------------------------------------------

    /** @test */
    public function finalize_returns_201_and_persists_eosb_to_employee(): void
    {
        $employee = Employee::factory()->create([
            'basic_salary' => 15_000.0,
            'hire_date'    => '2017-06-01',
        ]);

        $this->postJson("/api/hr/employees/{$employee->id}/eosb/finalize", [
            'end_date'           => now()->toDateString(),
            'termination_reason' => TerminationReason::EmployerTermination->value,
        ])->assertCreated()
            ->assertJsonStructure(['final_amount', 'dates']);

        $this->assertNotNull($employee->fresh()->eosb_amount);
        $this->assertNotNull($employee->fresh()->eosb_calculated_at);
    }

    /** @test */
    public function finalize_returns_422_on_invalid_input(): void
    {
        $employee = Employee::factory()->create();

        $this->postJson("/api/hr/employees/{$employee->id}/eosb/finalize", [
            'end_date'           => 'not-a-date',
            'termination_reason' => 'invalid_reason',
        ])->assertUnprocessable();
    }

    /** @test */
    public function finalize_records_termination_reason_on_employee(): void
    {
        $employee = Employee::factory()->create([
            'basic_salary' => 10_000.0,
            'hire_date'    => '2015-01-01',
        ]);

        $this->postJson("/api/hr/employees/{$employee->id}/eosb/finalize", [
            'end_date'           => now()->toDateString(),
            'termination_reason' => TerminationReason::Retirement->value,
        ])->assertCreated();

        $this->assertSame(
            TerminationReason::Retirement->value,
            $employee->fresh()->eosb_termination_reason,
        );
    }
}
