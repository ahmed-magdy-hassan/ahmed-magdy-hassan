<?php

declare(strict_types=1);

namespace App\Services\Payroll\Egypt;

use App\ValueObjects\Egypt\NosiContribution;
use InvalidArgumentException;

/**
 * Computes monthly NOSI (Egypt social-insurance) contributions.
 *
 * Rules:
 *  - Insured salary = gross salary clamped to [min_cap, max_cap] for the given year.
 *  - Employee deduction : insured_salary × 11%
 *  - Employer cost      : insured_salary × 18.75%
 *  - Work-injury premium: insured_salary × 1%  (employer only)
 *
 * Source: Law No. 79 of 1975 (Social Insurance Law), Art. 19 & schedules.
 */
final class NosiContributionService
{
    public function calculate(
        float $grossSalary,
        ?EgyptPayrollConfig $config = null,
    ): NosiContribution {
        if ($grossSalary < 0) {
            throw new InvalidArgumentException('Gross salary cannot be negative.');
        }

        $config        = $config ?? EgyptPayrollConfig::forYear((int) date('Y'));
        $insuredSalary = $config->clampInsuredSalary($grossSalary);

        $employeeAmount      = round($insuredSalary * EgyptPayrollConfig::NOSI_EMPLOYEE_RATE, 2);
        $employerBaseAmount  = round($insuredSalary * EgyptPayrollConfig::NOSI_EMPLOYER_RATE, 2);
        $workInjuryAmount    = round($insuredSalary * EgyptPayrollConfig::WORK_INJURY_RATE, 2);

        return new NosiContribution(
            grossSalary: $grossSalary,
            insuredSalary: $insuredSalary,
            employeeAmount: $employeeAmount,
            employerBaseAmount: $employerBaseAmount,
            workInjuryAmount: $workInjuryAmount,
            employeeRate: EgyptPayrollConfig::NOSI_EMPLOYEE_RATE,
            employerRate: EgyptPayrollConfig::NOSI_EMPLOYER_RATE,
            workInjuryRate: EgyptPayrollConfig::WORK_INJURY_RATE,
        );
    }
}
