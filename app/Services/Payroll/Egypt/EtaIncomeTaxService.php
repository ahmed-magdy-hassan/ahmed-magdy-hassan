<?php

declare(strict_types=1);

namespace App\Services\Payroll\Egypt;

use App\ValueObjects\Egypt\EtaTaxWithholding;
use InvalidArgumentException;

/**
 * Computes monthly ETA income-tax withholding for an Egyptian employee.
 *
 * Methodology (ETA payroll withholding):
 *  1. Monthly taxable = gross − NOSI_employee_deduction − (personal_exemption ÷ 12)
 *  2. Annualise: annual_taxable = monthly_taxable × 12
 *  3. Apply progressive brackets to annual_taxable → annual_tax
 *  4. Monthly withholding = annual_tax ÷ 12
 *
 * Source: Law No. 91 of 2005 (Income Tax Law) & Finance Laws 2022–2025.
 */
final class EtaIncomeTaxService
{
    public function calculate(
        float $monthlyGross,
        float $nosiEmployeeContribution,
        ?EgyptPayrollConfig $config = null,
    ): EtaTaxWithholding {
        if ($monthlyGross < 0) {
            throw new InvalidArgumentException('Monthly gross salary cannot be negative.');
        }
        if ($nosiEmployeeContribution < 0) {
            throw new InvalidArgumentException('NOSI employee contribution cannot be negative.');
        }

        $config = $config ?? EgyptPayrollConfig::forYear((int) date('Y'));

        $personalExemptionMonthly = $config->personalAnnualExemption / 12;
        $monthlyTaxable           = max(0.0, $monthlyGross - $nosiEmployeeContribution - $personalExemptionMonthly);
        $annualTaxable            = round($monthlyTaxable * 12, 2);

        ['total' => $annualTax, 'breakdown' => $breakdown] = $this->applyBrackets(
            $annualTaxable,
            $config->incomeTaxBrackets
        );

        $monthlyWithholding = round($annualTax / 12, 2);

        return new EtaTaxWithholding(
            monthlyGross: $monthlyGross,
            nosiEmployeeDeduction: $nosiEmployeeContribution,
            personalExemptionMonthly: round($personalExemptionMonthly, 2),
            monthlyTaxableIncome: round($monthlyTaxable, 2),
            annualTaxableIncome: $annualTaxable,
            annualTax: $annualTax,
            monthlyWithholding: $monthlyWithholding,
            bracketBreakdown: $breakdown,
        );
    }

    /**
     * Apply progressive tax brackets to an annual taxable income.
     * Returns ['total' => float, 'breakdown' => array].
     */
    private function applyBrackets(float $annualTaxable, array $brackets): array
    {
        $tax       = 0.0;
        $remaining = $annualTaxable;
        $breakdown = [];
        $floor     = 0.0;

        foreach ($brackets as $bracket) {
            if ($remaining <= 0.0) {
                break;
            }

            $ceiling = $bracket['up_to'];
            $rate    = (float) $bracket['rate'];
            $width   = $ceiling !== null ? ($ceiling - $floor) : PHP_FLOAT_MAX;
            $taxable = min($remaining, $width);
            $charge  = $taxable * $rate;

            if ($taxable > 0.0) {
                $breakdown[] = [
                    'from'    => $floor,
                    'to'      => $ceiling,
                    'rate'    => $rate,
                    'taxable' => round($taxable, 2),
                    'tax'     => round($charge, 2),
                ];
            }

            $tax       += $charge;
            $remaining -= $taxable;
            $floor      = $ceiling ?? $floor;
        }

        return ['total' => round($tax, 2), 'breakdown' => $breakdown];
    }
}
