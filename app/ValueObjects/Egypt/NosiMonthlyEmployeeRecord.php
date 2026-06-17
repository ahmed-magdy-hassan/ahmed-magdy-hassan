<?php

declare(strict_types=1);

namespace App\ValueObjects\Egypt;

/**
 * One employee's contribution line in a NOSI monthly contribution declaration (Form 1).
 *
 * NOSI Form 1 is submitted monthly — due by the 15th of the following month.
 * It lists every insured employee, their insured salary, and the contributions
 * to be transferred to NOSI's bank account.
 *
 * Source: Law No. 79/1975 (Social Insurance Law), Art. 32–34.
 */
final readonly class NosiMonthlyEmployeeRecord
{
    public function __construct(
        public string $employeeId,
        public string $name,
        public string $nosiNumber,
        public float  $grossSalary,
        public float  $insuredSalary,
        public float  $employeeContribution,   // 11 %
        public float  $employerBaseContribution, // 18.75 %
        public float  $workInjuryContribution,  // 1 %
        public float  $totalEmployerContribution,
        public float  $totalContribution,
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id'                 => $this->employeeId,
            'name'                        => $this->name,
            'nosi_number'                 => $this->nosiNumber,
            'gross_salary'                => $this->grossSalary,
            'insured_salary'              => $this->insuredSalary,
            'employee_contribution'       => $this->employeeContribution,
            'employer_base_contribution'  => $this->employerBaseContribution,
            'work_injury_contribution'    => $this->workInjuryContribution,
            'total_employer_contribution' => $this->totalEmployerContribution,
            'total_contribution'          => $this->totalContribution,
        ];
    }
}
