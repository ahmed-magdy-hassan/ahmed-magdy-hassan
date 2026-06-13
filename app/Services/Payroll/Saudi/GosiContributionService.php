<?php

declare(strict_types=1);

namespace App\Services\Payroll\Saudi;

use App\Enums\Payroll\GosiNationality;
use App\Enums\Payroll\GosiScheme;
use App\ValueObjects\Saudi\GosiContribution;
use InvalidArgumentException;

/**
 * Calculates monthly GOSI (General Organisation for Social Insurance) contributions.
 *
 * Rules applied:
 *  - Insured salary = gross salary capped at GosiConfig::WAGE_CEILING (SAR 45,000/month).
 *  - Saudi nationals (Old Scheme): employee 10 %, employer 10 % + 2 % occupational = 12 %.
 *  - Saudi nationals (New Scheme): employee 9 %, employer 9 % + 2 % occupational = 11 %.
 *  - Expatriates: employer 2 % occupational only; no employee deduction; no annuities.
 *
 * Sources: GOSI Law No. 74/1970; New Social Insurance Law 2025.
 */
final class GosiContributionService
{
    public function calculate(
        float $grossSalary,
        GosiNationality $nationality,
        GosiScheme $scheme = GosiScheme::Old,
    ): GosiContribution {
        if ($grossSalary < 0) {
            throw new InvalidArgumentException('Gross salary cannot be negative.');
        }

        $insuredSalary = min($grossSalary, GosiConfig::WAGE_CEILING);

        if ($nationality === GosiNationality::Expatriate) {
            return $this->expatriateContribution($grossSalary, $insuredSalary);
        }

        return $this->saudiContribution($grossSalary, $insuredSalary, $scheme);
    }

    private function expatriateContribution(float $gross, float $insured): GosiContribution
    {
        return new GosiContribution(
            grossSalary: $gross,
            insuredSalary: $insured,
            nationality: GosiNationality::Expatriate,
            scheme: GosiScheme::Old,  // irrelevant for expatriates
            employeeAnnuityAmount: 0.0,
            employerAnnuityAmount: 0.0,
            employerOccupationalAmount: round($insured * GosiConfig::OCCUPATIONAL_HAZARD_RATE, 2),
            employeeAnnuityRate: 0.0,
            employerAnnuityRate: 0.0,
            occupationalRate: GosiConfig::OCCUPATIONAL_HAZARD_RATE,
        );
    }

    private function saudiContribution(float $gross, float $insured, GosiScheme $scheme): GosiContribution
    {
        $employeeRate = $scheme === GosiScheme::Old
            ? GosiConfig::SAUDI_OLD_EMPLOYEE_ANNUITY_RATE
            : GosiConfig::SAUDI_NEW_EMPLOYEE_ANNUITY_RATE;

        $employerRate = $scheme === GosiScheme::Old
            ? GosiConfig::SAUDI_OLD_EMPLOYER_ANNUITY_RATE
            : GosiConfig::SAUDI_NEW_EMPLOYER_ANNUITY_RATE;

        return new GosiContribution(
            grossSalary: $gross,
            insuredSalary: $insured,
            nationality: GosiNationality::SaudiNational,
            scheme: $scheme,
            employeeAnnuityAmount: round($insured * $employeeRate, 2),
            employerAnnuityAmount: round($insured * $employerRate, 2),
            employerOccupationalAmount: round($insured * GosiConfig::OCCUPATIONAL_HAZARD_RATE, 2),
            employeeAnnuityRate: $employeeRate,
            employerAnnuityRate: $employerRate,
            occupationalRate: GosiConfig::OCCUPATIONAL_HAZARD_RATE,
        );
    }
}
