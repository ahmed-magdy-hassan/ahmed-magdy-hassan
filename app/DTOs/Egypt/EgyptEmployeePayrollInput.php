<?php

declare(strict_types=1);

namespace App\DTOs\Egypt;

/**
 * Input data required to compute one employee's Egypt monthly payroll.
 *
 * Used by EgyptPayrollRunService, EtaForm4Service, and NosiMonthlyReportService
 * when iterating over a company's workforce.  Deliberately thin — it carries only
 * what the calculation layer needs, keeping it decoupled from the Employee model.
 */
final readonly class EgyptEmployeePayrollInput
{
    public function __construct(
        /** Internal HR system employee ID. */
        public string  $employeeId,
        /** Full legal name (for ETA / NOSI reports). */
        public string  $name,
        /** 14-digit Egyptian national ID (NID). */
        public string  $nationalId,
        /** NOSI registration number. */
        public string  $nosiNumber,
        /** Monthly gross salary in EGP. */
        public float   $grossSalary,
        /**
         * Optional override for the NOSI-insured salary.
         * When null the NOSI service clamps grossSalary to the year cap.
         * Provide this when the employee was already registered at a fixed
         * insured salary different from current gross (e.g. after a mid-year cap change).
         */
        public ?float  $insuredSalaryOverride = null,
        /** True when the employee has a registered special-needs status (affects leave). */
        public bool    $specialNeeds = false,
        /** Hire date string (Y-m-d). Used for computing years-of-service entitlements. */
        public ?string $hireDate = null,
    ) {}

    public function toArray(): array
    {
        return [
            'employee_id'              => $this->employeeId,
            'name'                     => $this->name,
            'national_id'              => $this->nationalId,
            'nosi_number'              => $this->nosiNumber,
            'gross_salary'             => $this->grossSalary,
            'insured_salary_override'  => $this->insuredSalaryOverride,
            'special_needs'            => $this->specialNeeds,
            'hire_date'                => $this->hireDate,
        ];
    }
}
