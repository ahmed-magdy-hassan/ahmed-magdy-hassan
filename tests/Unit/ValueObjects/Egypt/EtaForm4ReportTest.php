<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects\Egypt;

use App\ValueObjects\Egypt\EtaForm4EmployeeRecord;
use App\ValueObjects\Egypt\EtaForm4Report;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EtaForm4ReportTest extends TestCase
{
    private function record(): EtaForm4EmployeeRecord
    {
        return new EtaForm4EmployeeRecord(
            employeeId: 'EMP-001',
            name: 'Ahmed Ali',
            nationalId: '29001011234567',
            quarterlyGross: 30_000.0,
            quarterlyNosiDeduction: 3_630.0,
            quarterlyPersonalExemption: 5_000.0,
            quarterlyTaxableIncome: 21_370.0,
            quarterlyTaxWithheld: 766.5,
            effectiveAnnualRate: 0.0469,
        );
    }

    private function report(int $quarter = 2): EtaForm4Report
    {
        return new EtaForm4Report(
            year: 2026,
            quarter: $quarter,
            periodLabel: "Q{$quarter} 2026",
            employees: [$this->record()],
            totalQuarterlyGross: 30_000.0,
            totalQuarterlyTaxWithheld: 766.5,
            totalEmployeeCount: 1,
        );
    }

    /** @test */
    public function q1_due_date_is_april_30(): void
    {
        $this->assertSame('2026-04-30', $this->report(1)->dueDate()->toDateString());
    }

    /** @test */
    public function q2_due_date_is_july_31(): void
    {
        $this->assertSame('2026-07-31', $this->report(2)->dueDate()->toDateString());
    }

    /** @test */
    public function q3_due_date_is_october_31(): void
    {
        $this->assertSame('2026-10-31', $this->report(3)->dueDate()->toDateString());
    }

    /** @test */
    public function q4_due_date_is_january_31_of_next_year(): void
    {
        $this->assertSame('2027-01-31', $this->report(4)->dueDate()->toDateString());
    }

    /** @test */
    public function months_returns_correct_array_for_each_quarter(): void
    {
        $this->assertSame([1, 2, 3],   $this->report(1)->months());
        $this->assertSame([4, 5, 6],   $this->report(2)->months());
        $this->assertSame([7, 8, 9],   $this->report(3)->months());
        $this->assertSame([10, 11, 12],$this->report(4)->months());
    }

    /** @test */
    public function invalid_quarter_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EtaForm4Report(2026, 0, 'bad', [], 0.0, 0.0, 0);
    }

    /** @test */
    public function to_array_has_all_required_keys(): void
    {
        $arr = $this->report()->toArray();

        foreach (['year', 'quarter', 'period_label', 'months', 'due_date',
                  'total_employee_count', 'total_quarterly_gross',
                  'total_quarterly_tax_withheld', 'employees'] as $key) {
            $this->assertArrayHasKey($key, $arr);
        }
    }

    /** @test */
    public function to_array_employees_contains_serialized_records(): void
    {
        $arr = $this->report()->toArray();
        $this->assertIsArray($arr['employees']);
        $this->assertCount(1, $arr['employees']);
        $this->assertSame('EMP-001', $arr['employees'][0]['employee_id']);
    }

    /** @test */
    public function employee_record_to_array_has_all_keys(): void
    {
        $arr = $this->record()->toArray();

        foreach (['employee_id', 'name', 'national_id', 'quarterly_gross',
                  'quarterly_nosi_deduction', 'quarterly_personal_exemption',
                  'quarterly_taxable_income', 'quarterly_tax_withheld',
                  'effective_annual_rate'] as $key) {
            $this->assertArrayHasKey($key, $arr);
        }
    }
}
