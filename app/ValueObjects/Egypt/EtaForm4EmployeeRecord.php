<?php

declare(strict_types=1);

namespace App\ValueObjects\Egypt;

/**
 * One employee's quarterly figures in an ETA Form 4 report.
 *
 * ETA Form 4 is Egypt's quarterly income-tax withholding return.
 * Submitted to the Egyptian Tax Authority (ETA) within 30 days after each quarter ends:
 *   Q1 (Jan–Mar) → due 30 April
 *   Q2 (Apr–Jun) → due 31 July
 *   Q3 (Jul–Sep) → due 31 October
 *   Q4 (Oct–Dec) → due 31 January (next year)
 *
 * Source: Law No. 91/2005, Art. 42 (quarterly withholding obligation).
 */
final readonly class EtaForm4EmployeeRecord
{
    public function __construct(
        public string $employeeId,
        public string $name,
        public string $nationalId,
        /** Gross salary summed over the quarter (3 months). */
        public float  $quarterlyGross,
        /** NOSI employee deduction summed over the quarter. */
        public float  $quarterlyNosiDeduction,
        /** Personal exemption allocated to this quarter (annual ÷ 4). */
        public float  $quarterlyPersonalExemption,
        /** Net taxable income for the quarter. */
        public float  $quarterlyTaxableIncome,
        /** Income tax withheld and remitted during the quarter. */
        public float  $quarterlyTaxWithheld,
        /** Effective annual rate (annual_tax / annual_taxable), 4 decimal places. */
        public float  $effectiveAnnualRate,
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id'                  => $this->employeeId,
            'name'                         => $this->name,
            'national_id'                  => $this->nationalId,
            'quarterly_gross'              => $this->quarterlyGross,
            'quarterly_nosi_deduction'     => $this->quarterlyNosiDeduction,
            'quarterly_personal_exemption' => $this->quarterlyPersonalExemption,
            'quarterly_taxable_income'     => $this->quarterlyTaxableIncome,
            'quarterly_tax_withheld'       => $this->quarterlyTaxWithheld,
            'effective_annual_rate'        => $this->effectiveAnnualRate,
        ];
    }
}
