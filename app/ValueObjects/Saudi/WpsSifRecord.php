<?php

declare(strict_types=1);

namespace App\ValueObjects\Saudi;

/**
 * One employee's wage record in a Saudi WPS SIF (Salary Information File).
 *
 * SIF format used by Mudad / Saudi banking channels:
 *   D|{employee_id}|{nationality_iso3}|{iban}|{basic}|{housing}|{other}|{deductions}|{net}
 *
 * Validation rules:
 *   - Employee ID: exactly 10 digits (Saudi national ID or IQAMA residency number).
 *   - IBAN: Saudi format — "SA" followed by exactly 22 digits (24 chars total).
 *   - Net salary must be > 0 (WPS requires a positive disbursement).
 *
 * Source: Saudi Central Bank (SAMA) WPS SIF Specification v2.
 */
final readonly class WpsSifRecord
{
    public function __construct(
        public string $employeeId,       // 10-digit national ID or IQAMA
        public string $iban,             // SA + 22 digits
        public float  $basicSalary,
        public float  $housingAllowance,
        public float  $otherAllowances,
        public float  $deductions,
        public float  $netSalary,
        public string $nationalityCode,  // ISO 3166-1 alpha-3, e.g. SAU, EGY, IND
    ) {}

    /**
     * Returns an array of validation error strings (empty = valid).
     *
     * @return string[]
     */
    public function validationErrors(): array
    {
        $errors = [];

        if (!preg_match('/^\d{10}$/', $this->employeeId)) {
            $errors[] = "Employee {$this->employeeId}: ID must be exactly 10 digits.";
        }

        if (!preg_match('/^SA\d{22}$/', $this->iban)) {
            $errors[] = "Employee {$this->employeeId}: IBAN must be SA followed by 22 digits.";
        }

        if ($this->netSalary <= 0) {
            $errors[] = "Employee {$this->employeeId}: net salary must be greater than zero.";
        }

        return $errors;
    }

    public function isValid(): bool
    {
        return empty($this->validationErrors());
    }

    /** Renders the pipe-delimited detail line for the SIF file. */
    public function toSifLine(): string
    {
        return implode('|', [
            'D',
            $this->employeeId,
            strtoupper($this->nationalityCode),
            $this->iban,
            number_format($this->basicSalary,      2, '.', ''),
            number_format($this->housingAllowance,  2, '.', ''),
            number_format($this->otherAllowances,   2, '.', ''),
            number_format($this->deductions,        2, '.', ''),
            number_format($this->netSalary,         2, '.', ''),
        ]);
    }

    public function toArray(): array
    {
        return [
            'employee_id'       => $this->employeeId,
            'iban'              => $this->iban,
            'nationality_code'  => $this->nationalityCode,
            'basic_salary'      => $this->basicSalary,
            'housing_allowance' => $this->housingAllowance,
            'other_allowances'  => $this->otherAllowances,
            'deductions'        => $this->deductions,
            'net_salary'        => $this->netSalary,
        ];
    }
}
