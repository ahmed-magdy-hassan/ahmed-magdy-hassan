<?php

declare(strict_types=1);

namespace App\ValueObjects\Egypt;

/**
 * Full monthly payroll result for an Egyptian-market employee.
 *
 * Covers NOSI contributions (HRIST-191), ETA income-tax withholding (HRIST-191),
 * and the training-fund employer cost introduced by Labour Law 14/2025 (HRIST-192).
 */
final readonly class EgyptPayrollResult
{
    public function __construct(
        public readonly float              $grossSalary,
        public readonly NosiContribution   $nosi,
        public readonly EtaTaxWithholding  $incomeTax,
        public readonly float              $trainingFundMonthly,
        public readonly int                $taxYear,
    ) {}

    /** Amount deposited to the employee's bank account. */
    public function netSalary(): float
    {
        return round(
            $this->grossSalary - $this->nosi->employeeAmount - $this->incomeTax->monthlyWithholding,
            2
        );
    }

    /** Total monthly cost to the employer (gross + all employer-side obligations). */
    public function totalEmployerCost(): float
    {
        return round(
            $this->grossSalary
            + $this->nosi->totalEmployerAmount()
            + $this->trainingFundMonthly,
            2
        );
    }

    public function toArray(): array
    {
        return [
            'tax_year'              => $this->taxYear,
            'gross_salary'          => $this->grossSalary,
            'nosi'                  => $this->nosi->toArray(),
            'income_tax'            => $this->incomeTax->toArray(),
            'training_fund_monthly' => $this->trainingFundMonthly,
            'net_salary'            => $this->netSalary(),
            'total_employer_cost'   => $this->totalEmployerCost(),
        ];
    }
}
