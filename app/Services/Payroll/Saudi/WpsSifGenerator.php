<?php

declare(strict_types=1);

namespace App\Services\Payroll\Saudi;

use App\ValueObjects\Saudi\WpsSifRecord;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Generates a Saudi WPS SIF (Salary Information File) from employee wage records.
 *
 * File structure (pipe-delimited, CRLF line endings):
 *   H|{version}|{payer_id}|{year}|{month}|{count}|{total_net}
 *   D|{employee_id}|{nationality}|{iban}|{basic}|{housing}|{other}|{deductions}|{net}
 *   …one D-line per employee…
 *   T|{count}|{total_net}
 *
 * Source: Saudi Central Bank (SAMA) WPS SIF Specification v2.
 */
final class WpsSifGenerator
{
    private const SIF_VERSION = '02';

    /**
     * @param string          $employerBankId  Bank-assigned payer/employer ID
     * @param string          $payrollMonth    YYYY-MM
     * @param WpsSifRecord[]  $records
     *
     * @throws InvalidArgumentException if records is empty or any record is invalid
     */
    public function generate(
        string $employerBankId,
        string $payrollMonth,
        array $records,
    ): string {
        if (empty($records)) {
            throw new InvalidArgumentException('SIF file requires at least one employee record.');
        }

        $errors = [];
        foreach ($records as $record) {
            foreach ($record->validationErrors() as $e) {
                $errors[] = $e;
            }
        }

        if (!empty($errors)) {
            throw new InvalidArgumentException(
                'SIF validation failed: ' . implode(' | ', $errors)
            );
        }

        $date     = Carbon::parse($payrollMonth . '-01');
        $count    = count($records);
        $totalNet = array_sum(array_map(fn(WpsSifRecord $r) => $r->netSalary, $records));

        $lines   = [];
        $lines[] = $this->headerLine($employerBankId, $date, $count, $totalNet);

        foreach ($records as $record) {
            $lines[] = $record->toSifLine();
        }

        $lines[] = $this->trailerLine($count, $totalNet);

        return implode("\r\n", $lines) . "\r\n";
    }

    private function headerLine(
        string $payerId,
        Carbon $date,
        int $count,
        float $totalNet,
    ): string {
        return implode('|', [
            'H',
            self::SIF_VERSION,
            $payerId,
            $date->format('Y'),
            $date->format('m'),
            (string) $count,
            number_format($totalNet, 2, '.', ''),
        ]);
    }

    private function trailerLine(int $count, float $totalNet): string
    {
        return implode('|', [
            'T',
            (string) $count,
            number_format($totalNet, 2, '.', ''),
        ]);
    }
}
