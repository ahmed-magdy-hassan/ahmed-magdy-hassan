<?php

declare(strict_types=1);

namespace App\Services\Payroll\Egypt;

use App\ValueObjects\Egypt\EgyptPayrollResult;
use InvalidArgumentException;

/**
 * Orchestrates the full Egypt monthly payroll calculation (HRIST-191 + HRIST-192).
 *
 * Calculation order:
 *  1. NOSI contributions (NosiContributionService)   → employee deduction + employer cost
 *  2. ETA income-tax withholding (EtaIncomeTaxService) → monthly withholding
 *  3. Training-fund employer cost (LabourLaw14Service)  → per-employee monthly amount
 *  4. Assemble EgyptPayrollResult
 */
final class EgyptPayrollCalculator
{
    public function __construct(
        private readonly NosiContributionService $nosiService,
        private readonly EtaIncomeTaxService     $taxService,
        private readonly LabourLaw14Service      $labourLaw14,
    ) {}

    /**
     * Calculate monthly payroll for a single employee.
     *
     * @param float                 $grossSalary  Monthly gross (EGP)
     * @param int                   $companyHeadcount  Total staff (used for training-fund threshold)
     * @param EgyptPayrollConfig|null $config     Override for tax year; defaults to current year
     */
    public function calculate(
        float $grossSalary,
        int $companyHeadcount = 1,
        ?EgyptPayrollConfig $config = null,
    ): EgyptPayrollResult {
        if ($grossSalary < 0) {
            throw new InvalidArgumentException('Gross salary cannot be negative.');
        }
        if ($companyHeadcount < 1) {
            throw new InvalidArgumentException('Company headcount must be at least 1.');
        }

        $config = $config ?? EgyptPayrollConfig::forYear((int) date('Y'));

        $nosi = $this->nosiService->calculate($grossSalary, $config);

        $incomeTax = $this->taxService->calculate(
            monthlyGross: $grossSalary,
            nosiEmployeeContribution: $nosi->employeeAmount,
            config: $config,
        );

        $trainingFund = $this->labourLaw14->trainingFundMonthly(
            $config->nosiMinInsuredSalary,
            $companyHeadcount,
        );

        return new EgyptPayrollResult(
            grossSalary: $grossSalary,
            nosi: $nosi,
            incomeTax: $incomeTax,
            trainingFundMonthly: $trainingFund,
            taxYear: $config->year,
        );
    }
}
