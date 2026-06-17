<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects\Egypt;

use App\ValueObjects\Egypt\NosiMonthlyEmployeeRecord;
use App\ValueObjects\Egypt\NosiMonthlyReport;
use PHPUnit\Framework\TestCase;

final class NosiMonthlyReportTest extends TestCase
{
    private function record(): NosiMonthlyEmployeeRecord
    {
        return new NosiMonthlyEmployeeRecord(
            employeeId: 'EMP-001',
            name: 'Ahmed Ali',
            nosiNumber: 'NOSI-001',
            grossSalary: 8_000.0,
            insuredSalary: 8_000.0,
            employeeContribution: 880.0,
            employerBaseContribution: 1_500.0,
            workInjuryContribution: 80.0,
            totalEmployerContribution: 1_580.0,
            totalContribution: 2_460.0,
        );
    }

    private function report(int $month = 6): NosiMonthlyReport
    {
        return new NosiMonthlyReport(
            year: 2026,
            month: $month,
            periodLabel: 'June 2026',
            employees: [$this->record()],
            totalGrossSalary: 8_000.0,
            totalInsuredSalary: 8_000.0,
            totalEmployeeContribution: 880.0,
            totalEmployerBaseContribution: 1_500.0,
            totalWorkInjuryContribution: 80.0,
            totalEmployerContribution: 1_580.0,
            grandTotalContribution: 2_460.0,
        );
    }

    /** @test */
    public function due_date_is_15th_of_next_month(): void
    {
        $this->assertSame('2026-07-15', $this->report(6)->dueDate()->toDateString());
    }

    /** @test */
    public function due_date_for_december_wraps_to_next_year(): void
    {
        $this->assertSame('2027-01-15', $this->report(12)->dueDate()->toDateString());
    }

    /** @test */
    public function due_date_for_january_is_february_15(): void
    {
        $this->assertSame('2026-02-15', $this->report(1)->dueDate()->toDateString());
    }

    /** @test */
    public function to_array_has_all_required_keys(): void
    {
        $arr = $this->report()->toArray();

        foreach (['year', 'month', 'period_label', 'due_date', 'employee_count',
                  'total_gross_salary', 'total_insured_salary',
                  'total_employee_contribution', 'total_employer_base_contribution',
                  'total_work_injury_contribution', 'total_employer_contribution',
                  'grand_total_contribution', 'employees'] as $key) {
            $this->assertArrayHasKey($key, $arr);
        }
    }

    /** @test */
    public function to_array_employee_count_matches_employees_array(): void
    {
        $arr = $this->report()->toArray();
        $this->assertSame(count($arr['employees']), $arr['employee_count']);
    }

    /** @test */
    public function employee_record_to_array_has_all_keys(): void
    {
        $arr = $this->record()->toArray();

        foreach (['employee_id', 'name', 'nosi_number', 'gross_salary', 'insured_salary',
                  'employee_contribution', 'employer_base_contribution',
                  'work_injury_contribution', 'total_employer_contribution',
                  'total_contribution'] as $key) {
            $this->assertArrayHasKey($key, $arr);
        }
    }
}
