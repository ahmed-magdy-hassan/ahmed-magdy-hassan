<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects\Saudi;

use App\ValueObjects\Saudi\WpsSifRecord;
use PHPUnit\Framework\TestCase;

final class WpsSifRecordTest extends TestCase
{
    private function valid(): WpsSifRecord
    {
        return new WpsSifRecord(
            employeeId: '1234567890',
            iban: 'SA4420000001234567891234',
            basicSalary: 8_000.0,
            housingAllowance: 2_000.0,
            otherAllowances: 500.0,
            deductions: 100.0,
            netSalary: 10_400.0,
            nationalityCode: 'SAU',
        );
    }

    /** @test */
    public function valid_record_has_no_validation_errors(): void
    {
        $this->assertEmpty($this->valid()->validationErrors());
        $this->assertTrue($this->valid()->isValid());
    }

    /** @test */
    public function invalid_iban_triggers_error(): void
    {
        $record = new WpsSifRecord(
            employeeId: '1234567890',
            iban: 'GB12345678901234567890',  // UK IBAN — invalid for SA
            basicSalary: 8_000.0,
            housingAllowance: 0.0,
            otherAllowances: 0.0,
            deductions: 0.0,
            netSalary: 8_000.0,
            nationalityCode: 'GBR',
        );

        $errors = $record->validationErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('IBAN', $errors[0]);
        $this->assertFalse($record->isValid());
    }

    /** @test */
    public function short_employee_id_triggers_error(): void
    {
        $record = new WpsSifRecord(
            employeeId: '12345',             // too short
            iban: 'SA4420000001234567891234',
            basicSalary: 5_000.0,
            housingAllowance: 0.0,
            otherAllowances: 0.0,
            deductions: 0.0,
            netSalary: 5_000.0,
            nationalityCode: 'SAU',
        );

        $errors = $record->validationErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('ID', $errors[0]);
    }

    /** @test */
    public function zero_net_salary_triggers_error(): void
    {
        $record = new WpsSifRecord(
            employeeId: '1234567890',
            iban: 'SA4420000001234567891234',
            basicSalary: 0.0,
            housingAllowance: 0.0,
            otherAllowances: 0.0,
            deductions: 0.0,
            netSalary: 0.0,
            nationalityCode: 'SAU',
        );

        $errors = $record->validationErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('net salary', $errors[0]);
    }

    /** @test */
    public function sif_line_starts_with_d_prefix(): void
    {
        $line = $this->valid()->toSifLine();
        $this->assertStringStartsWith('D|', $line);
    }

    /** @test */
    public function sif_line_contains_employee_id_and_iban(): void
    {
        $line = $this->valid()->toSifLine();
        $this->assertStringContainsString('1234567890', $line);
        $this->assertStringContainsString('SA4420000001234567891234', $line);
    }

    /** @test */
    public function sif_line_contains_net_salary_formatted_to_two_decimals(): void
    {
        $line = $this->valid()->toSifLine();
        $this->assertStringContainsString('10400.00', $line);
    }

    /** @test */
    public function sif_line_nationality_is_uppercased(): void
    {
        $record = new WpsSifRecord(
            employeeId: '1234567890',
            iban: 'SA4420000001234567891234',
            basicSalary: 5_000.0,
            housingAllowance: 0.0,
            otherAllowances: 0.0,
            deductions: 0.0,
            netSalary: 5_000.0,
            nationalityCode: 'egy',  // lowercase input
        );

        $this->assertStringContainsString('EGY', $record->toSifLine());
    }

    /** @test */
    public function to_array_contains_all_keys(): void
    {
        $arr = $this->valid()->toArray();

        foreach (['employee_id', 'iban', 'nationality_code', 'basic_salary',
                  'housing_allowance', 'other_allowances', 'deductions', 'net_salary'] as $key) {
            $this->assertArrayHasKey($key, $arr, "Missing key: {$key}");
        }
    }
}
