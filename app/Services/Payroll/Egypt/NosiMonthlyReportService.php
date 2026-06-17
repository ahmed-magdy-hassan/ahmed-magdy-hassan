<?php

declare(strict_types=1);

namespace App\Services\Payroll\Egypt;

use App\DTOs\Egypt\EgyptEmployeePayrollInput;
use App\ValueObjects\Egypt\NosiMonthlyEmployeeRecord;
use App\ValueObjects\Egypt\NosiMonthlyReport;
use InvalidArgumentException;

/**
 * Generates the NOSI Form 1 monthly contribution declaration.
 *
 * NOSI Form 1 lists every insured employee, their insured salary, and the
 * employee + employer contributions to be transferred to NOSI's collection account.
 * Due by the 15th of the following month.
 *
 * Source: Law No. 79/1975, Art. 32–34; NOSI executive regulations.
 */
final class NosiMonthlyReportService
{
    private const MONTH_NAMES = [
        1 => 'January', 2 => 'February', 3 => 'March',
        4 => 'April',   5 => 'May',      6 => 'June',
        7 => 'July',    8 => 'August',   9 => 'September',
        10 => 'October', 11 => 'November', 12 => 'December',
    ];

    public function __construct(
        private readonly NosiContributionService $nosiService,
    ) {}

    /**
     * @param  EgyptEmployeePayrollInput[]  $employees
     * @param  int  $year   Calendar year
     * @param  int  $month  Calendar month (1–12)
     */
    public function generate(
        array $employees,
        int $year,
        int $month,
        ?EgyptPayrollConfig $config = null,
    ): NosiMonthlyReport {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException("Month must be 1–12, got {$month}.");
        }
        if (empty($employees)) {
            throw new InvalidArgumentException('At least one employee is required.');
        }

        $config  = $config ?? EgyptPayrollConfig::forYear($year);
        $records = [];
        $totals  = [
            'gross'           => 0.0,
            'insured'         => 0.0,
            'employee'        => 0.0,
            'employer_base'   => 0.0,
            'work_injury'     => 0.0,
            'employer_total'  => 0.0,
            'grand'           => 0.0,
        ];

        foreach ($employees as $employee) {
            $nosi = $this->nosiService->calculate($employee->grossSalary, $config);

            $records[] = new NosiMonthlyEmployeeRecord(
                employeeId: $employee->employeeId,
                name: $employee->name,
                nosiNumber: $employee->nosiNumber,
                grossSalary: $nosi->grossSalary,
                insuredSalary: $nosi->insuredSalary,
                employeeContribution: $nosi->employeeAmount,
                employerBaseContribution: $nosi->employerBaseAmount,
                workInjuryContribution: $nosi->workInjuryAmount,
                totalEmployerContribution: $nosi->totalEmployerAmount(),
                totalContribution: $nosi->totalContribution(),
            );

            $totals['gross']          += $nosi->grossSalary;
            $totals['insured']        += $nosi->insuredSalary;
            $totals['employee']       += $nosi->employeeAmount;
            $totals['employer_base']  += $nosi->employerBaseAmount;
            $totals['work_injury']    += $nosi->workInjuryAmount;
            $totals['employer_total'] += $nosi->totalEmployerAmount();
            $totals['grand']          += $nosi->totalContribution();
        }

        $periodLabel = self::MONTH_NAMES[$month] . ' ' . $year;

        return new NosiMonthlyReport(
            year: $year,
            month: $month,
            periodLabel: $periodLabel,
            employees: $records,
            totalGrossSalary:              round($totals['gross'], 2),
            totalInsuredSalary:            round($totals['insured'], 2),
            totalEmployeeContribution:     round($totals['employee'], 2),
            totalEmployerBaseContribution: round($totals['employer_base'], 2),
            totalWorkInjuryContribution:   round($totals['work_injury'], 2),
            totalEmployerContribution:     round($totals['employer_total'], 2),
            grandTotalContribution:        round($totals['grand'], 2),
        );
    }
}
