<?php

declare(strict_types=1);

namespace App\ValueObjects\Egypt;

/**
 * Immutable result of an ETA (Egyptian Tax Authority) monthly income-tax withholding calculation.
 *
 * Egypt uses annual progressive brackets; the monthly withholding is 1/12 of the
 * estimated annual liability computed from the annualised monthly taxable income.
 */
final readonly class EtaTaxWithholding
{
    public function __construct(
        public readonly float $monthlyGross,
        public readonly float $nosiEmployeeDeduction,
        public readonly float $personalExemptionMonthly,
        public readonly float $monthlyTaxableIncome,
        public readonly float $annualTaxableIncome,
        public readonly float $annualTax,
        public readonly float $monthlyWithholding,
        public readonly array  $bracketBreakdown,
    ) {}

    public function effectiveAnnualRate(): float
    {
        if ($this->annualTaxableIncome <= 0) {
            return 0.0;
        }

        return round($this->annualTax / $this->annualTaxableIncome, 4);
    }

    public function toArray(): array
    {
        return [
            'monthly_gross'               => $this->monthlyGross,
            'nosi_employee_deduction'     => $this->nosiEmployeeDeduction,
            'personal_exemption_monthly'  => $this->personalExemptionMonthly,
            'monthly_taxable_income'      => $this->monthlyTaxableIncome,
            'annual_taxable_income'       => $this->annualTaxableIncome,
            'annual_tax'                  => $this->annualTax,
            'monthly_withholding'         => $this->monthlyWithholding,
            'effective_annual_rate'       => $this->effectiveAnnualRate(),
            'bracket_breakdown'           => $this->bracketBreakdown,
        ];
    }
}
