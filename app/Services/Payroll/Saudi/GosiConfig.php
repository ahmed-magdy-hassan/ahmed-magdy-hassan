<?php

declare(strict_types=1);

namespace App\Services\Payroll\Saudi;

/**
 * GOSI (General Organisation for Social Insurance) rate configuration.
 *
 * Saudi nationals (Old Scheme — registered before 2025-07-01):
 *   Employee: 10 % annuities
 *   Employer: 10 % annuities + 2 % occupational hazards = 12 %
 *
 * Saudi nationals (New Scheme — registered from 2025-07-01):
 *   New Social Insurance Law reduces entry rates for new registrants.
 *   Employee: 9 % annuities
 *   Employer: 9 % annuities + 2 % occupational hazards = 11 %
 *
 * Expatriates (all schemes):
 *   Employee: 0 %
 *   Employer: 2 % occupational hazards only (no annuities)
 *
 * Wage ceiling: SAR 45,000 / month for contribution calculation.
 * Salary changes must be reported to GOSI within 15 days.
 *
 * Sources: GOSI Law No. 74/1970; New Social Insurance Law 2025.
 */
final class GosiConfig
{
    // Saudi national — Old Scheme
    public const SAUDI_OLD_EMPLOYEE_ANNUITY_RATE = 0.10;   // 10 %
    public const SAUDI_OLD_EMPLOYER_ANNUITY_RATE = 0.10;   // 10 %

    // Saudi national — New Scheme (effective 2025-07-01 for new entrants)
    public const SAUDI_NEW_EMPLOYEE_ANNUITY_RATE = 0.09;   // 9 %
    public const SAUDI_NEW_EMPLOYER_ANNUITY_RATE = 0.09;   // 9 %

    // Occupational-hazards insurance — employer-only, all nationalities
    public const OCCUPATIONAL_HAZARD_RATE = 0.02;          // 2 %

    // Monthly wage ceiling (SAR)
    public const WAGE_CEILING = 45_000.0;

    // Date from which the New Scheme applies to new registrants
    public const NEW_SCHEME_EFFECTIVE_DATE = '2025-07-01';

    // Salary changes must be reported within this many days
    public const SALARY_CHANGE_REPORT_DAYS = 15;

    private function __construct() {}
}
