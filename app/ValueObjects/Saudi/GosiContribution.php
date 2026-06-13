<?php

declare(strict_types=1);

namespace App\ValueObjects\Saudi;

use App\Enums\Payroll\GosiNationality;
use App\Enums\Payroll\GosiScheme;

/**
 * Immutable result of a GOSI (Saudi social insurance) contribution calculation.
 *
 * For Saudi nationals both employee and employer pay annuity contributions.
 * For expatriates only the employer pays occupational-hazard contributions.
 */
final readonly class GosiContribution
{
    public function __construct(
        public float           $grossSalary,
        public float           $insuredSalary,
        public GosiNationality $nationality,
        public GosiScheme      $scheme,
        public float           $employeeAnnuityAmount,
        public float           $employerAnnuityAmount,
        public float           $employerOccupationalAmount,
        public float           $employeeAnnuityRate,
        public float           $employerAnnuityRate,
        public float           $occupationalRate,
    ) {}

    /** Amount deducted from the employee's payslip. */
    public function employeeDeduction(): float
    {
        return $this->employeeAnnuityAmount;
    }

    /** Total GOSI cost borne by the employer (annuity share + occupational). */
    public function totalEmployerCost(): float
    {
        return round($this->employerAnnuityAmount + $this->employerOccupationalAmount, 2);
    }

    /** Combined employee + employer contribution. */
    public function totalContribution(): float
    {
        return round($this->employeeDeduction() + $this->totalEmployerCost(), 2);
    }

    public function toArray(): array
    {
        return [
            'gross_salary'                 => $this->grossSalary,
            'insured_salary'               => $this->insuredSalary,
            'nationality'                  => $this->nationality->value,
            'nationality_label'            => $this->nationality->label(),
            'scheme'                       => $this->scheme->value,
            'scheme_label'                 => $this->scheme->label(),
            'employee_annuity_amount'      => $this->employeeAnnuityAmount,
            'employer_annuity_amount'      => $this->employerAnnuityAmount,
            'employer_occupational_amount' => $this->employerOccupationalAmount,
            'employee_deduction'           => $this->employeeDeduction(),
            'total_employer_cost'          => $this->totalEmployerCost(),
            'total_contribution'           => $this->totalContribution(),
            'rates' => [
                'employee_annuity' => $this->employeeAnnuityRate,
                'employer_annuity' => $this->employerAnnuityRate,
                'occupational'     => $this->occupationalRate,
            ],
        ];
    }
}
