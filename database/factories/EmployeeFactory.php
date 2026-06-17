<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Payroll\GosiNationality;
use App\Enums\Payroll\GosiScheme;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
final class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'company_id'   => 1,
            'name'         => $this->faker->name(),
            'email'        => $this->faker->unique()->safeEmail(),
            'hire_date'    => $this->faker->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'basic_salary' => $this->faker->randomFloat(2, 3_000, 50_000),
            'nationality'  => $this->faker->randomElement(['SAU', 'EGY', 'IND', 'PAK', 'PHL']),

            // EOSB — null by default; set via state or direct attribute override
            'eosb_amount'             => null,
            'eosb_termination_reason' => null,
            'eosb_calculated_at'      => null,

            // Egypt — null until Egyptian payroll is configured for this employee
            'eta_tax_id'                   => null,
            'nosi_number'                  => null,
            'nosi_insured_salary'          => null,
            'special_needs'                => false,
            'last_eta_monthly_withholding' => null,
            'nosi_registered_at'           => null,

            // GOSI — null until Saudi GOSI is configured
            'gosi_nationality'      => null,
            'gosi_scheme'           => null,
            'gosi_number'           => null,
            'gosi_registered_at'    => null,
            'gosi_insured_salary'   => null,
            'gosi_salary_updated_at' => null,
        ];
    }

    /** Employee enrolled with Egypt NOSI + ETA (ready for Egyptian payroll). */
    public function egyptRegistered(): static
    {
        return $this->state(fn (array $attributes) => [
            'nationality'         => 'EGY',
            'eta_tax_id'          => $this->faker->numerify('########'),
            'nosi_number'         => $this->faker->numerify('###########'),
            'nosi_insured_salary' => $attributes['basic_salary'],
            'nosi_registered_at'  => $attributes['hire_date'],
        ]);
    }

    /** Saudi national enrolled with GOSI under the New scheme (post-July 2025). */
    public function saudiGosi(): static
    {
        return $this->state(fn (array $attributes) => [
            'nationality'        => 'SAU',
            'gosi_nationality'   => GosiNationality::SaudiNational->value,
            'gosi_scheme'        => GosiScheme::New->value,
            'gosi_number'        => $this->faker->numerify('##########'),
            'gosi_registered_at' => $attributes['hire_date'],
            'gosi_insured_salary' => $attributes['basic_salary'],
        ]);
    }

    /** Expatriate enrolled with GOSI (hazard-only contribution). */
    public function expatriateGosi(): static
    {
        return $this->state(fn (array $attributes) => [
            'gosi_nationality'   => GosiNationality::Expatriate->value,
            'gosi_scheme'        => GosiScheme::New->value,
            'gosi_number'        => $this->faker->numerify('##########'),
            'gosi_registered_at' => $attributes['hire_date'],
            'gosi_insured_salary' => $attributes['basic_salary'],
        ]);
    }
}
