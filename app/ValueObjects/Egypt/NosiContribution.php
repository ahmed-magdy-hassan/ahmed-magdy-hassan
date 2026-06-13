<?php

declare(strict_types=1);

namespace App\ValueObjects\Egypt;

/**
 * Immutable result of a NOSI (Egypt social-insurance) contribution calculation.
 *
 * Employee contribution is deducted from the payslip.
 * Employer contribution and work-injury premium are employer costs only.
 */
final readonly class NosiContribution
{
    public function __construct(
        public readonly float $grossSalary,
        public readonly float $insuredSalary,
        public readonly float $employeeAmount,
        public readonly float $employerBaseAmount,
        public readonly float $workInjuryAmount,
        public readonly float $employeeRate,
        public readonly float $employerRate,
        public readonly float $workInjuryRate,
    ) {}

    public function totalEmployerAmount(): float
    {
        return round($this->employerBaseAmount + $this->workInjuryAmount, 2);
    }

    public function totalContribution(): float
    {
        return round($this->employeeAmount + $this->totalEmployerAmount(), 2);
    }

    public function toArray(): array
    {
        return [
            'gross_salary'         => $this->grossSalary,
            'insured_salary'       => $this->insuredSalary,
            'employee_amount'      => $this->employeeAmount,
            'employer_base_amount' => $this->employerBaseAmount,
            'work_injury_amount'   => $this->workInjuryAmount,
            'total_employer'       => $this->totalEmployerAmount(),
            'total_contribution'   => $this->totalContribution(),
            'rates' => [
                'employee'     => $this->employeeRate,
                'employer'     => $this->employerRate,
                'work_injury'  => $this->workInjuryRate,
            ],
        ];
    }
}
