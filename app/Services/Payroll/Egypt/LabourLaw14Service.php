<?php

declare(strict_types=1);

namespace App\Services\Payroll\Egypt;

use InvalidArgumentException;

/**
 * Computes employer obligations introduced by Egypt Labour Law No. 14 of 2025
 * (effective 1 September 2025).
 *
 * Rules covered (HRIST-192):
 *  - Mandatory 3% annual increment on the employee's NOSI-insured salary
 *  - Training-fund contribution: 0.25% of min NOSI salary, per employee, for ≥30-staff companies
 *  - Updated leave entitlements (15 → 21 days, 45 for special-needs)
 *  - Maternity leave: 4 months (up from 3), max 3 times
 *  - Statutory notice periods
 */
final class LabourLaw14Service
{
    /** Effective date of Law 14/2025 — rules do not apply to periods before this. */
    public const EFFECTIVE_DATE = '2025-09-01';

    // -----------------------------------------------------------------------
    // Annual increment (Art. 5 — wage protection)
    // -----------------------------------------------------------------------

    /**
     * Minimum statutory annual increment = 3% of the employee's NOSI-insured salary.
     * Applied at the employee's annual review / contract anniversary.
     */
    public function annualIncrementAmount(float $insuredSalary): float
    {
        if ($insuredSalary < 0) {
            throw new InvalidArgumentException('Insured salary cannot be negative.');
        }

        return round($insuredSalary * EgyptPayrollConfig::ANNUAL_INCREMENT_RATE, 2);
    }

    // -----------------------------------------------------------------------
    // Training-fund contribution (Art. 23 — vocational training)
    // -----------------------------------------------------------------------

    /**
     * Monthly employer contribution to the training fund.
     *
     * Formula: 0.25% × min_insured_salary × 1 (per employee)
     * Threshold: employer must have ≥30 employees.
     * Returns 0 for companies below the threshold.
     */
    public function trainingFundMonthly(float $minInsuredSalary, int $headcount): float
    {
        if ($headcount < EgyptPayrollConfig::TRAINING_FUND_MIN_HEADCOUNT) {
            return 0.0;
        }

        return round($minInsuredSalary * EgyptPayrollConfig::TRAINING_FUND_RATE, 2);
    }

    /**
     * Total monthly training-fund cost for the whole company (all employees).
     */
    public function trainingFundTotal(float $minInsuredSalary, int $headcount): float
    {
        return round($this->trainingFundMonthly($minInsuredSalary, $headcount) * $headcount, 2);
    }

    // -----------------------------------------------------------------------
    // Leave entitlements (Art. 47 — annual leave)
    // -----------------------------------------------------------------------

    /**
     * Annual leave entitlement in working days per Labour Law No. 14/2025.
     *
     * Year 1 of employment: 15 days
     * Year 2 onward:        21 days
     * Special-needs:        45 days (any tenure)
     */
    public function leaveEntitlementDays(int $yearsOfService, bool $specialNeeds = false): int
    {
        if ($yearsOfService < 0) {
            throw new InvalidArgumentException('Years of service cannot be negative.');
        }

        if ($specialNeeds) {
            return 45;
        }

        return $yearsOfService >= 2 ? 21 : 15;
    }

    // -----------------------------------------------------------------------
    // Maternity leave (Art. 91)
    // -----------------------------------------------------------------------

    /**
     * Maternity leave duration in calendar days (4 months = 120 days).
     * Maximum 3 times over the employee's tenure.
     */
    public function maternityLeaveDays(): int
    {
        return 120;
    }

    public function maternityLeaveMaxOccurrences(): int
    {
        return 3;
    }

    // -----------------------------------------------------------------------
    // Notice periods (Art. 110 — termination)
    // -----------------------------------------------------------------------

    /**
     * Statutory notice period in days before termination.
     *
     * < 10 years service: 60 days
     * ≥ 10 years service: 90 days
     *
     * Under Law 14/2025 termination is processed via the labour court; this
     * notice period applies to voluntary/mutual-consent endings.
     */
    public function noticePeriodDays(int $yearsOfService): int
    {
        return $yearsOfService >= 10 ? 90 : 60;
    }

    // -----------------------------------------------------------------------
    // Severance / indemnity (Art. 112)
    // -----------------------------------------------------------------------

    /**
     * Minimum statutory severance in months of gross wage.
     *
     * Employer-initiated termination: 2 months per year of service
     * Resignation after ≥5 years:     1 month per year of service
     */
    public function severanceMonths(int $yearsOfService, bool $isEmployerTermination): float
    {
        if ($yearsOfService <= 0) {
            return 0.0;
        }

        return $isEmployerTermination
            ? $yearsOfService * 2.0
            : ($yearsOfService >= 5 ? $yearsOfService * 1.0 : 0.0);
    }
}
