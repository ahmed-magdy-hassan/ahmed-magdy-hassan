<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll\Egypt;

use App\Services\Payroll\Egypt\EgyptPayrollConfig;
use PHPUnit\Framework\TestCase;

final class EgyptPayrollConfigTest extends TestCase
{
    // -----------------------------------------------------------------------
    // forYear() — direct lookups
    // -----------------------------------------------------------------------

    /** @test */
    public function for_year_2026_returns_correct_nosi_caps(): void
    {
        $config = EgyptPayrollConfig::forYear(2026);

        $this->assertSame(2026, $config->year);
        $this->assertSame(1_800.0, $config->nosiMinInsuredSalary);
        $this->assertSame(16_441.0, $config->nosiMaxInsuredSalary);
    }

    /** @test */
    public function for_year_2022_returns_earliest_known_caps(): void
    {
        $config = EgyptPayrollConfig::forYear(2022);

        $this->assertSame(1_500.0, $config->nosiMinInsuredSalary);
        $this->assertSame(9_400.0, $config->nosiMaxInsuredSalary);
    }

    /** @test */
    public function for_year_2026_returns_correct_personal_exemption(): void
    {
        $config = EgyptPayrollConfig::forYear(2026);

        $this->assertSame(20_000.0, $config->personalAnnualExemption);
    }

    /** @test */
    public function for_year_returns_six_tax_brackets(): void
    {
        $config = EgyptPayrollConfig::forYear(2026);

        $this->assertCount(6, $config->incomeTaxBrackets);
    }

    /** @test */
    public function tax_brackets_start_at_zero_rate(): void
    {
        $config    = EgyptPayrollConfig::forYear(2026);
        $firstRate = $config->incomeTaxBrackets[0]['rate'];

        $this->assertSame(0.0, $firstRate);
    }

    /** @test */
    public function tax_brackets_top_rate_is_27_5_percent(): void
    {
        $config   = EgyptPayrollConfig::forYear(2026);
        $brackets = $config->incomeTaxBrackets;
        $topRate  = end($brackets)['rate'];

        $this->assertSame(0.275, $topRate);
    }

    /** @test */
    public function each_year_from_2023_to_2027_has_explicit_bracket_entry(): void
    {
        // Verify every year has its own entry so callers never silently hit a fallback.
        foreach (range(2023, 2027) as $year) {
            $config = EgyptPayrollConfig::forYear($year);

            $this->assertCount(6, $config->incomeTaxBrackets, "Year {$year} should have 6 brackets");
            $this->assertSame(0.0,   $config->incomeTaxBrackets[0]['rate'], "Year {$year}: first bracket must be 0%");
            $this->assertSame(0.275, $config->incomeTaxBrackets[5]['rate'], "Year {$year}: top bracket must be 27.5%");
        }
    }

    /** @test */
    public function brackets_for_2024_match_2023_finance_law_unchanged(): void
    {
        $config2023 = EgyptPayrollConfig::forYear(2023);
        $config2024 = EgyptPayrollConfig::forYear(2024);

        $this->assertSame($config2023->incomeTaxBrackets, $config2024->incomeTaxBrackets);
    }

    /** @test */
    public function brackets_for_2025_match_2023_finance_law_unchanged(): void
    {
        $config2023 = EgyptPayrollConfig::forYear(2023);
        $config2025 = EgyptPayrollConfig::forYear(2025);

        $this->assertSame($config2023->incomeTaxBrackets, $config2025->incomeTaxBrackets);
    }

    /** @test */
    public function brackets_for_2026_match_2023_finance_law_unchanged(): void
    {
        $config2023 = EgyptPayrollConfig::forYear(2023);
        $config2026 = EgyptPayrollConfig::forYear(2026);

        $this->assertSame($config2023->incomeTaxBrackets, $config2026->incomeTaxBrackets);
    }

    // -----------------------------------------------------------------------
    // forYear() — fallback paths
    // -----------------------------------------------------------------------

    /** @test */
    public function for_year_beyond_schedule_falls_back_to_latest_nosi_cap(): void
    {
        // 2030 is beyond our schedule (max is 2027)
        $config2030 = EgyptPayrollConfig::forYear(2030);
        $config2027 = EgyptPayrollConfig::forYear(2027);

        $this->assertSame($config2027->nosiMaxInsuredSalary, $config2030->nosiMaxInsuredSalary);
        // But year property reflects what was requested
        $this->assertSame(2030, $config2030->year);
    }

    /** @test */
    public function for_year_before_exemption_schedule_falls_back_to_earliest(): void
    {
        // Year 2020 is before 2022 (earliest in PERSONAL_EXEMPTION)
        $config2020 = EgyptPayrollConfig::forYear(2020);
        $config2022 = EgyptPayrollConfig::forYear(2022);

        $this->assertSame($config2022->personalAnnualExemption, $config2020->personalAnnualExemption);
    }

    // -----------------------------------------------------------------------
    // clampInsuredSalary()
    // -----------------------------------------------------------------------

    /** @test */
    public function clamp_returns_salary_when_within_range(): void
    {
        $config = EgyptPayrollConfig::forYear(2026);
        $this->assertSame(8_000.0, $config->clampInsuredSalary(8_000.0));
    }

    /** @test */
    public function clamp_returns_minimum_when_salary_is_below(): void
    {
        $config = EgyptPayrollConfig::forYear(2026);
        $this->assertSame(1_800.0, $config->clampInsuredSalary(500.0));
    }

    /** @test */
    public function clamp_returns_maximum_when_salary_exceeds_cap(): void
    {
        $config = EgyptPayrollConfig::forYear(2026);
        $this->assertSame(16_441.0, $config->clampInsuredSalary(50_000.0));
    }

    /** @test */
    public function clamp_returns_exact_minimum_boundary(): void
    {
        $config = EgyptPayrollConfig::forYear(2026);
        $this->assertSame(1_800.0, $config->clampInsuredSalary(1_800.0));
    }

    /** @test */
    public function clamp_returns_exact_maximum_boundary(): void
    {
        $config = EgyptPayrollConfig::forYear(2026);
        $this->assertSame(16_441.0, $config->clampInsuredSalary(16_441.0));
    }

    // -----------------------------------------------------------------------
    // Constants
    // -----------------------------------------------------------------------

    /** @test */
    public function nosi_rates_are_correct(): void
    {
        $this->assertSame(0.11,   EgyptPayrollConfig::NOSI_EMPLOYEE_RATE);
        $this->assertSame(0.1875, EgyptPayrollConfig::NOSI_EMPLOYER_RATE);
        $this->assertSame(0.01,   EgyptPayrollConfig::WORK_INJURY_RATE);
    }

    /** @test */
    public function labour_law_14_constants_are_correct(): void
    {
        $this->assertSame(0.03,   EgyptPayrollConfig::ANNUAL_INCREMENT_RATE);
        $this->assertSame(0.0025, EgyptPayrollConfig::TRAINING_FUND_RATE);
        $this->assertSame(30,     EgyptPayrollConfig::TRAINING_FUND_MIN_HEADCOUNT);
    }
}
