<?php

declare(strict_types=1);

namespace App\Services\Payroll\Egypt;

use App\DTOs\Egypt\EgyptEmployeePayrollInput;
use App\ValueObjects\Egypt\EgyptPayrollRunResult;
use InvalidArgumentException;

/**
 * Runs Egypt monthly payroll for every employee in a company in a single call.
 *
 * Orchestration order per employee:
 *   1. NOSI contribution  (NosiContributionService)
 *   2. ETA income-tax     (EtaIncomeTaxService)
 *   3. Training-fund cost (LabourLaw14Service)
 *   4. Assemble EgyptPayrollResult
 *
 * The company-level training-fund threshold (≥30 staff) is evaluated once
 * against the total headcount; the per-employee cost uses the year's NOSI
 * minimum insured salary as the base.
 */
final class EgyptPayrollRunService
{
    public function __construct(
        private readonly EgyptPayrollCalculator $calculator,
    ) {}

    /**
     * @param  EgyptEmployeePayrollInput[]  $employees
     * @param  int                          $year   Calendar year for the payroll run
     * @param  int                          $month  Calendar month (1–12)
     * @param  EgyptPayrollConfig|null      $config Defaults to current year
     *
     * @throws InvalidArgumentException when employees array is empty or month is out of range
     */
    public function run(
        array $employees,
        int $year,
        int $month,
        ?EgyptPayrollConfig $config = null,
    ): EgyptPayrollRunResult {
        if (empty($employees)) {
            throw new InvalidArgumentException('At least one employee is required for a payroll run.');
        }
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Month must be 1–12, got {$month}.");
        }

        $config      = $config ?? EgyptPayrollConfig::forYear($year);
        $headcount   = count($employees);
        $rows        = [];
        $totals      = [
            'gross'          => 0.0,
            'net'            => 0.0,
            'nosi_employee'  => 0.0,
            'nosi_employer'  => 0.0,
            'tax_withheld'   => 0.0,
            'training_fund'  => 0.0,
            'employer_cost'  => 0.0,
        ];

        foreach ($employees as $input) {
            $payroll = $this->calculator->calculate(
                grossSalary: $input->grossSalary,
                companyHeadcount: $headcount,
                config: $config,
            );

            $rows[] = ['input' => $input, 'payroll' => $payroll];

            $totals['gross']         += $payroll->grossSalary;
            $totals['net']           += $payroll->netSalary();
            $totals['nosi_employee'] += $payroll->nosi->employeeAmount;
            $totals['nosi_employer'] += $payroll->nosi->totalEmployerAmount();
            $totals['tax_withheld']  += $payroll->incomeTax->monthlyWithholding;
            $totals['training_fund'] += $payroll->trainingFundMonthly;
            $totals['employer_cost'] += $payroll->totalEmployerCost();
        }

        return new EgyptPayrollRunResult(
            year: $year,
            month: $month,
            taxYear: $config->year,
            employees: $rows,
            totalGrossSalary:   round($totals['gross'], 2),
            totalNetSalary:     round($totals['net'], 2),
            totalNosiEmployee:  round($totals['nosi_employee'], 2),
            totalNosiEmployer:  round($totals['nosi_employer'], 2),
            totalTaxWithheld:   round($totals['tax_withheld'], 2),
            totalTrainingFund:  round($totals['training_fund'], 2),
            totalEmployerCost:  round($totals['employer_cost'], 2),
        );
    }
}
