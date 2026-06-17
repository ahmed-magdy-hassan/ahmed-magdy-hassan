<?php

declare(strict_types=1);

namespace App\ValueObjects\Egypt;

/**
 * Aggregate result of running Egypt monthly payroll for a whole company.
 *
 * Produced by EgyptPayrollRunService::run().
 * Each element of $employees is ['input' => EgyptEmployeePayrollInput, 'payroll' => EgyptPayrollResult].
 */
final readonly class EgyptPayrollRunResult
{
    /**
     * @param array<array{input: \App\DTOs\Egypt\EgyptEmployeePayrollInput, payroll: EgyptPayrollResult}> $employees
     */
    public function __construct(
        public int   $year,
        public int   $month,
        public int   $taxYear,
        public array $employees,
        public float $totalGrossSalary,
        public float $totalNetSalary,
        public float $totalNosiEmployee,
        public float $totalNosiEmployer,
        public float $totalTaxWithheld,
        public float $totalTrainingFund,
        public float $totalEmployerCost,
    ) {}

    public function employeeCount(): int
    {
        return count($this->employees);
    }

    public function toArray(): array
    {
        return [
            'year'                  => $this->year,
            'month'                 => $this->month,
            'tax_year'              => $this->taxYear,
            'employee_count'        => $this->employeeCount(),
            'totals' => [
                'gross_salary'   => $this->totalGrossSalary,
                'net_salary'     => $this->totalNetSalary,
                'nosi_employee'  => $this->totalNosiEmployee,
                'nosi_employer'  => $this->totalNosiEmployer,
                'tax_withheld'   => $this->totalTaxWithheld,
                'training_fund'  => $this->totalTrainingFund,
                'employer_cost'  => $this->totalEmployerCost,
            ],
            'employees' => array_map(function (array $row): array {
                return [
                    'employee'  => $row['input']->toArray(),
                    'payroll'   => $row['payroll']->toArray(),
                ];
            }, $this->employees),
        ];
    }
}
