<?php

declare(strict_types=1);

namespace App\Services\Payroll\Egypt;

use InvalidArgumentException;

/**
 * Year-scoped configuration for Egypt payroll calculations.
 *
 * NOSI (National Organisation for Social Insurance) caps increase ~15% per year
 * through 2027 per the schedule mandated by Law 79/1975 and subsequent decrees.
 * ETA income-tax brackets are from Law No. 91 of 2005 as amended by the 2022/23 budget.
 */
final class EgyptPayrollConfig
{
    // -----------------------------------------------------------------------
    // Rates (fixed by law, not year-dependent)
    // -----------------------------------------------------------------------

    public const NOSI_EMPLOYEE_RATE   = 0.11;    // 11%  — Law 79/1975 Art. 19
    public const NOSI_EMPLOYER_RATE   = 0.1875;  // 18.75% — Law 79/1975 Art. 19
    public const WORK_INJURY_RATE     = 0.01;    // 1% employer-only

    // Annual increment minimum per Labour Law No. 14/2025
    public const ANNUAL_INCREMENT_RATE = 0.03;   // 3% of insured salary

    // Training-fund rate per Law 14/2025 (employer, ≥30 staff only)
    public const TRAINING_FUND_RATE         = 0.0025;  // 0.25% of min insured salary
    public const TRAINING_FUND_MIN_HEADCOUNT = 30;

    // -----------------------------------------------------------------------
    // Year-specific NOSI salary caps (EGP/month)
    // -----------------------------------------------------------------------

    private const NOSI_CAPS = [
        2022 => ['min' => 1_500.0, 'max' =>  9_400.0],
        2023 => ['min' => 1_700.0, 'max' => 10_810.0],
        2024 => ['min' => 1_800.0, 'max' => 12_432.0],
        2025 => ['min' => 1_800.0, 'max' => 14_297.0],
        2026 => ['min' => 1_800.0, 'max' => 16_441.0],
        2027 => ['min' => 1_800.0, 'max' => 18_907.0],
    ];

    // -----------------------------------------------------------------------
    // Year-specific ETA income-tax brackets (annual EGP, applied after exemption)
    // Each row: ['up_to' => float|null, 'rate' => float]
    // null up_to = no ceiling (top bracket)
    // -----------------------------------------------------------------------

    private const TAX_BRACKETS = [
        // 2023 budget onward (Finance Law 2022)
        2023 => [
            ['up_to' =>  40_000.0, 'rate' => 0.000],
            ['up_to' =>  55_000.0, 'rate' => 0.100],
            ['up_to' =>  70_000.0, 'rate' => 0.150],
            ['up_to' => 200_000.0, 'rate' => 0.200],
            ['up_to' => 400_000.0, 'rate' => 0.225],
            ['up_to' =>       null, 'rate' => 0.275],
        ],
    ];

    // Annual personal exemption (EGP) per Finance Law
    private const PERSONAL_EXEMPTION = [
        2022 => 15_000.0,
        2023 => 20_000.0,
        2024 => 20_000.0,
        2025 => 20_000.0,
    ];

    // -----------------------------------------------------------------------

    private function __construct(
        public readonly int   $year,
        public readonly float $nosiMinInsuredSalary,
        public readonly float $nosiMaxInsuredSalary,
        public readonly float $personalAnnualExemption,
        public readonly array $incomeTaxBrackets,
    ) {}

    public static function forYear(int $year): self
    {
        // NOSI caps: fall back to latest known year if beyond schedule
        $caps = self::NOSI_CAPS[$year]
            ?? self::NOSI_CAPS[max(array_keys(self::NOSI_CAPS))];

        // Tax brackets: fall back to latest known year
        $bracketYear = $year >= min(array_keys(self::TAX_BRACKETS))
            ? min($year, max(array_keys(self::TAX_BRACKETS)))
            : min(array_keys(self::TAX_BRACKETS));
        $brackets = self::TAX_BRACKETS[$bracketYear];

        // Personal exemption: fall back to latest known
        $exemptionYear = $year >= min(array_keys(self::PERSONAL_EXEMPTION))
            ? min($year, max(array_keys(self::PERSONAL_EXEMPTION)))
            : min(array_keys(self::PERSONAL_EXEMPTION));
        $exemption = self::PERSONAL_EXEMPTION[$exemptionYear];

        return new self(
            year: $year,
            nosiMinInsuredSalary: $caps['min'],
            nosiMaxInsuredSalary: $caps['max'],
            personalAnnualExemption: $exemption,
            incomeTaxBrackets: $brackets,
        );
    }

    /** Clamp a salary to the NOSI-insurable range for this year. */
    public function clampInsuredSalary(float $grossSalary): float
    {
        return max($this->nosiMinInsuredSalary, min($grossSalary, $this->nosiMaxInsuredSalary));
    }
}
