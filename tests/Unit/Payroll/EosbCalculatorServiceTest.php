<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Enums\Payroll\TerminationReason;
use App\Services\Payroll\EosbCalculatorService;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EosbCalculatorServiceTest extends TestCase
{
    private EosbCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EosbCalculatorService();
    }

    /** @test */
    public function termination_under_5_years_uses_half_month_formula(): void
    {
        // 3 years exactly at SAR 10,000/month
        // Expected: 3 × (10,000 / 2) = 15,000
        $result = $this->service->calculate(
            monthlyWage: 10_000.0,
            startDate: Carbon::parse('2020-01-01'),
            endDate: Carbon::parse('2023-01-01'),
            reason: TerminationReason::EmployerTermination,
        );

        $this->assertEqualsWithDelta(15_000.0, $result->finalAmount, 50.0);
        $this->assertEquals(1.0, $result->resignationMultiplier);
    }

    /** @test */
    public function termination_over_5_years_uses_combined_formula(): void
    {
        // 7 years at SAR 10,000/month
        // Expected: (5 × 5,000) + (2 × 10,000) = 25,000 + 20,000 = 45,000
        $result = $this->service->calculate(
            monthlyWage: 10_000.0,
            startDate: Carbon::parse('2016-01-01'),
            endDate: Carbon::parse('2023-01-01'),
            reason: TerminationReason::EmployerTermination,
        );

        $this->assertEqualsWithDelta(45_000.0, $result->finalAmount, 50.0);
    }

    /** @test */
    public function resignation_under_2_years_yields_zero(): void
    {
        $result = $this->service->calculate(
            monthlyWage: 10_000.0,
            startDate: Carbon::parse('2022-06-01'),
            endDate: Carbon::parse('2023-06-01'),
            reason: TerminationReason::Resignation,
        );

        $this->assertEquals(0.0, $result->finalAmount);
        $this->assertEquals(0.0, $result->resignationMultiplier);
    }

    /** @test */
    public function resignation_between_2_and_5_years_yields_one_third(): void
    {
        // 3 years resignation: full = 15,000 × 1/3 ≈ 5,000
        $result = $this->service->calculate(
            monthlyWage: 10_000.0,
            startDate: Carbon::parse('2020-01-01'),
            endDate: Carbon::parse('2023-01-01'),
            reason: TerminationReason::Resignation,
        );

        $this->assertEqualsWithDelta(5_000.0, $result->finalAmount, 50.0);
        $this->assertEqualsWithDelta(1 / 3, $result->resignationMultiplier, 0.001);
    }

    /** @test */
    public function resignation_between_5_and_10_years_yields_two_thirds(): void
    {
        // 7 years resignation: full = 45,000 × 2/3 = 30,000
        $result = $this->service->calculate(
            monthlyWage: 10_000.0,
            startDate: Carbon::parse('2016-01-01'),
            endDate: Carbon::parse('2023-01-01'),
            reason: TerminationReason::Resignation,
        );

        $this->assertEqualsWithDelta(30_000.0, $result->finalAmount, 50.0);
        $this->assertEqualsWithDelta(2 / 3, $result->resignationMultiplier, 0.001);
    }

    /** @test */
    public function resignation_over_10_years_yields_full_entitlement(): void
    {
        $result = $this->service->calculate(
            monthlyWage: 10_000.0,
            startDate: Carbon::parse('2011-01-01'),
            endDate: Carbon::parse('2023-01-01'),
            reason: TerminationReason::Resignation,
        );

        $this->assertEquals(1.0, $result->resignationMultiplier);
        $this->assertEquals($result->fullEntitlement, $result->finalAmount);
    }

    /** @test */
    public function mutual_consent_receives_full_entitlement(): void
    {
        $byEmployer = $this->service->calculate(
            monthlyWage: 10_000.0,
            startDate: Carbon::parse('2020-01-01'),
            endDate: Carbon::parse('2023-01-01'),
            reason: TerminationReason::EmployerTermination,
        );

        $mutual = $this->service->calculate(
            monthlyWage: 10_000.0,
            startDate: Carbon::parse('2020-01-01'),
            endDate: Carbon::parse('2023-01-01'),
            reason: TerminationReason::MutualConsent,
        );

        $this->assertEquals($byEmployer->finalAmount, $mutual->finalAmount);
    }

    /** @test */
    public function pro_rata_applied_for_partial_years(): void
    {
        // 1.5 years at SAR 12,000/month
        // Expected ≈ 1.5 × 6,000 = 9,000
        $result = $this->service->calculate(
            monthlyWage: 12_000.0,
            startDate: Carbon::parse('2022-01-01'),
            endDate: Carbon::parse('2023-07-01'),
            reason: TerminationReason::EmployerTermination,
        );

        $this->assertGreaterThan(8_500.0, $result->finalAmount);
        $this->assertLessThan(9_500.0, $result->finalAmount);
    }

    /** @test */
    public function throws_when_end_date_before_start_date(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('End date must be after start date');

        $this->service->calculate(
            monthlyWage: 10_000.0,
            startDate: Carbon::parse('2023-01-01'),
            endDate: Carbon::parse('2022-01-01'),
            reason: TerminationReason::EmployerTermination,
        );
    }

    /** @test */
    public function throws_on_negative_wage(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->calculate(
            monthlyWage: -500.0,
            startDate: Carbon::parse('2020-01-01'),
            endDate: Carbon::parse('2023-01-01'),
            reason: TerminationReason::EmployerTermination,
        );
    }

    /** @test */
    public function result_to_array_contains_all_keys(): void
    {
        $result = $this->service->calculate(
            monthlyWage: 10_000.0,
            startDate: Carbon::parse('2020-01-01'),
            endDate: Carbon::parse('2023-01-01'),
            reason: TerminationReason::EmployerTermination,
        );

        $arr = $result->toArray();

        $this->assertArrayHasKey('monthly_wage', $arr);
        $this->assertArrayHasKey('service_years', $arr);
        $this->assertArrayHasKey('termination_reason', $arr);
        $this->assertArrayHasKey('full_entitlement', $arr);
        $this->assertArrayHasKey('resignation_multiplier', $arr);
        $this->assertArrayHasKey('final_amount', $arr);
        $this->assertArrayHasKey('breakdown', $arr);
    }
}
