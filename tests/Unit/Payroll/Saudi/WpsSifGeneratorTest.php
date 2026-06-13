<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll\Saudi;

use App\Services\Payroll\Saudi\WpsSifGenerator;
use App\ValueObjects\Saudi\WpsSifRecord;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class WpsSifGeneratorTest extends TestCase
{
    private WpsSifGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new WpsSifGenerator();
    }

    private function record(
        string $id     = '1234567890',
        float  $net    = 10_000.0,
        string $iban   = 'SA4420000001234567891234',
    ): WpsSifRecord {
        return new WpsSifRecord(
            employeeId: $id,
            iban: $iban,
            basicSalary: $net,
            housingAllowance: 0.0,
            otherAllowances: 0.0,
            deductions: 0.0,
            netSalary: $net,
            nationalityCode: 'SAU',
        );
    }

    /** @test */
    public function single_record_produces_header_detail_trailer(): void
    {
        $sif   = $this->generator->generate('RJHI', '2026-06', [$this->record()]);
        $lines = explode("\r\n", rtrim($sif, "\r\n"));

        $this->assertCount(3, $lines);
        $this->assertStringStartsWith('H|', $lines[0]);
        $this->assertStringStartsWith('D|', $lines[1]);
        $this->assertStringStartsWith('T|', $lines[2]);
    }

    /** @test */
    public function header_contains_employer_bank_id_and_payroll_month(): void
    {
        $sif    = $this->generator->generate('RJHI', '2026-06', [$this->record()]);
        $header = explode("\r\n", $sif)[0];

        $this->assertStringContainsString('RJHI', $header);
        $this->assertStringContainsString('2026', $header);
        $this->assertStringContainsString('06',   $header);
    }

    /** @test */
    public function header_record_count_matches_number_of_employees(): void
    {
        $records = [$this->record('1234567890'), $this->record('0987654321', 12_000.0, 'SA4420000009876543210987')];
        $sif     = $this->generator->generate('RJHI', '2026-06', $records);
        $header  = explode('|', explode("\r\n", $sif)[0]);

        // H|{version}|{payer}|{year}|{month}|{count}|{total}
        $this->assertSame('2', $header[5]);
    }

    /** @test */
    public function trailer_total_matches_sum_of_net_salaries(): void
    {
        $records = [$this->record('1234567890', 8_000.0), $this->record('0987654321', 12_000.0, 'SA4420000009876543210987')];
        $sif     = $this->generator->generate('RJHI', '2026-06', $records);
        $lines   = explode("\r\n", rtrim($sif, "\r\n"));
        $trailer = $lines[count($lines) - 1];

        $this->assertStringContainsString('20000.00', $trailer);
    }

    /** @test */
    public function file_uses_crlf_line_endings(): void
    {
        $sif = $this->generator->generate('RJHI', '2026-06', [$this->record()]);
        $this->assertStringContainsString("\r\n", $sif);
    }

    /** @test */
    public function empty_records_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->generator->generate('RJHI', '2026-06', []);
    }

    /** @test */
    public function invalid_iban_in_record_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SIF validation failed');

        $bad = new WpsSifRecord(
            employeeId: '1234567890',
            iban: 'BADIBAN',
            basicSalary: 5_000.0,
            housingAllowance: 0.0,
            otherAllowances: 0.0,
            deductions: 0.0,
            netSalary: 5_000.0,
            nationalityCode: 'SAU',
        );

        $this->generator->generate('RJHI', '2026-06', [$bad]);
    }

    /** @test */
    public function multiple_employees_produce_correct_detail_line_count(): void
    {
        $records = [
            $this->record('1111111111'),
            $this->record('2222222222', 15_000.0, 'SA4420000002222222222222'),
            $this->record('3333333333', 9_000.0,  'SA4420000003333333333333'),
        ];

        $sif   = $this->generator->generate('RJHI', '2026-06', $records);
        $lines = explode("\r\n", rtrim($sif, "\r\n"));

        // Header + 3 detail + trailer = 5 lines
        $this->assertCount(5, $lines);
    }
}
