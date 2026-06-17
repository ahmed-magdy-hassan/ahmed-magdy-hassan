<?php

declare(strict_types=1);

namespace App\Services\Payroll\Egypt;

use App\DTOs\Egypt\EgyptEmployeePayrollInput;
use App\ValueObjects\Egypt\EtaForm4EmployeeRecord;
use App\ValueObjects\Egypt\EtaForm4Report;
use InvalidArgumentException;

/**
 * Generates the ETA Form 4 quarterly income-tax withholding return.
 *
 * ETA Form 4 is filed with the Egyptian Tax Authority (ETA) within 30 days
 * after each quarter closes.  It reconciles every employee's taxable income
 * and the income-tax amount withheld and remitted by the employer during the quarter.
 *
 * Methodology (constant-salary assumption):
 *   monthly_payroll  = EtaIncomeTaxService::calculate(employee.gross_salary, …)
 *   quarterly_*      = monthly_* × 3
 *
 * When a salary changed mid-quarter, the caller should pass the average monthly
 * gross for the quarter as employee.grossSalary.
 *
 * Source: Law No. 91/2005 (Income Tax Law), Art. 42; ETA Circular 3/2023.
 */
final class EtaForm4Service
{
    private const QUARTER_NAMES = [
        1 => 'Q1 (Jan–Mar)',
        2 => 'Q2 (Apr–Jun)',
        3 => 'Q3 (Jul–Sep)',
        4 => 'Q4 (Oct–Dec)',
    ];

    public function __construct(
        private readonly EtaIncomeTaxService     $taxService,
        private readonly NosiContributionService $nosiService,
    ) {}

    /**
     * @param  EgyptEmployeePayrollInput[]  $employees
     * @param  int  $year     Tax year (Gregorian)
     * @param  int  $quarter  1 = Jan-Mar, 2 = Apr-Jun, 3 = Jul-Sep, 4 = Oct-Dec
     */
    public function generate(
        array $employees,
        int $year,
        int $quarter,
        ?EgyptPayrollConfig $config = null,
    ): EtaForm4Report {
        if ($quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException("Quarter must be 1–4, got {$quarter}.");
        }
        if (empty($employees)) {
            throw new InvalidArgumentException('At least one employee is required.');
        }

        $config  = $config ?? EgyptPayrollConfig::forYear($year);
        $records = [];
        $totalGross = 0.0;
        $totalTax   = 0.0;

        foreach ($employees as $employee) {
            $nosi = $this->nosiService->calculate($employee->grossSalary, $config);
            $tax  = $this->taxService->calculate(
                monthlyGross: $employee->grossSalary,
                nosiEmployeeContribution: $nosi->employeeAmount,
                config: $config,
            );

            // Quarter = 3 months
            $quarterlyGross             = round($employee->grossSalary * 3, 2);
            $quarterlyNosi              = round($nosi->employeeAmount * 3, 2);
            $quarterlyPersonalExemption = round(($config->personalAnnualExemption / 12) * 3, 2);
            $quarterlyTaxable           = round($tax->monthlyTaxableIncome * 3, 2);
            $quarterlyTax               = round($tax->monthlyWithholding * 3, 2);

            $records[] = new EtaForm4EmployeeRecord(
                employeeId: $employee->employeeId,
                name: $employee->name,
                nationalId: $employee->nationalId,
                quarterlyGross: $quarterlyGross,
                quarterlyNosiDeduction: $quarterlyNosi,
                quarterlyPersonalExemption: $quarterlyPersonalExemption,
                quarterlyTaxableIncome: $quarterlyTaxable,
                quarterlyTaxWithheld: $quarterlyTax,
                effectiveAnnualRate: $tax->effectiveAnnualRate(),
            );

            $totalGross += $quarterlyGross;
            $totalTax   += $quarterlyTax;
        }

        $label = self::QUARTER_NAMES[$quarter] . ' ' . $year;

        return new EtaForm4Report(
            year: $year,
            quarter: $quarter,
            periodLabel: $label,
            employees: $records,
            totalQuarterlyGross: round($totalGross, 2),
            totalQuarterlyTaxWithheld: round($totalTax, 2),
            totalEmployeeCount: count($records),
        );
    }
}
