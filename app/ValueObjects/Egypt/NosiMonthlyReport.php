<?php

declare(strict_types=1);

namespace App\ValueObjects\Egypt;

use Carbon\Carbon;

/**
 * Company-level NOSI monthly contribution report (Form 1).
 *
 * Covers all insured employees for one calendar month.
 * The payment + declaration is due by the 15th of the following month.
 *
 * Source: Law No. 79/1975, Art. 32; NOSI executive regulations.
 */
final readonly class NosiMonthlyReport
{
    /**
     * @param NosiMonthlyEmployeeRecord[] $employees
     */
    public function __construct(
        public int    $year,
        public int    $month,
        public string $periodLabel,
        public array  $employees,
        public float  $totalGrossSalary,
        public float  $totalInsuredSalary,
        public float  $totalEmployeeContribution,
        public float  $totalEmployerBaseContribution,
        public float  $totalWorkInjuryContribution,
        public float  $totalEmployerContribution,
        public float  $grandTotalContribution,
    ) {}

    /** Date by which NOSI Form 1 and payment must be submitted (15th of next month). */
    public function dueDate(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1)
            ->addMonthNoOverflow()
            ->startOfMonth()
            ->addDays(14);   // day 15
    }

    public function toArray(): array
    {
        return [
            'year'                              => $this->year,
            'month'                             => $this->month,
            'period_label'                      => $this->periodLabel,
            'due_date'                          => $this->dueDate()->toDateString(),
            'total_gross_salary'                => $this->totalGrossSalary,
            'total_insured_salary'              => $this->totalInsuredSalary,
            'total_employee_contribution'       => $this->totalEmployeeContribution,
            'total_employer_base_contribution'  => $this->totalEmployerBaseContribution,
            'total_work_injury_contribution'    => $this->totalWorkInjuryContribution,
            'total_employer_contribution'       => $this->totalEmployerContribution,
            'grand_total_contribution'          => $this->grandTotalContribution,
            'employee_count'                    => count($this->employees),
            'employees'                         => array_map(
                fn(NosiMonthlyEmployeeRecord $r) => $r->toArray(),
                $this->employees,
            ),
        ];
    }
}
