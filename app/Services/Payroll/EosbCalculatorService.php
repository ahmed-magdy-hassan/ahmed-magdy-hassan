<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Enums\Payroll\TerminationReason;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Calculates Saudi End-of-Service Benefit (EOSB / gratuity).
 *
 * Saudi Labour Law:
 *   Art. 84 — Full entitlement on employer termination / mutual consent / contract expiry.
 *   Art. 85 — Reduced entitlement on resignation:
 *              < 2 yrs → 0 | 2–5 yrs → 1/3 | 5–10 yrs → 2/3 | 10+ yrs → full
 *
 * Entitlement formula:
 *   Years 1–5  : 0.5 × monthly_wage per year
 *   Years 5+   : 1.0 × monthly_wage per additional year
 *   Partial years calculated pro-rata (days / 365.25).
 */
final class EosbCalculatorService
{
    public function calculate(
        float $monthlyWage,
        Carbon $startDate,
        Carbon $endDate,
        TerminationReason $reason,
    ): EosbResult {
        if ($endDate->lt($startDate)) {
            throw new InvalidArgumentException('End date must be after start date.');
        }

        if ($monthlyWage < 0) {
            throw new InvalidArgumentException('Monthly wage cannot be negative.');
        }

        $totalYears      = $this->serviceYears($startDate, $endDate);
        $fullEntitlement = $this->fullEntitlement($monthlyWage, $totalYears);
        $multiplier      = $this->resignationMultiplier($reason, $totalYears);
        $finalAmount     = round($fullEntitlement * $multiplier, 2);

        return new EosbResult(
            monthlyWage: $monthlyWage,
            serviceYears: $totalYears,
            terminationReason: $reason,
            fullEntitlement: round($fullEntitlement, 2),
            resignationMultiplier: $multiplier,
            finalAmount: $finalAmount,
            breakdown: $this->buildBreakdown($monthlyWage, $totalYears, $multiplier),
        );
    }

    private function serviceYears(Carbon $start, Carbon $end): float
    {
        return $start->diffInDays($end) / 365.25;
    }

    private function fullEntitlement(float $wage, float $years): float
    {
        if ($years <= 0) {
            return 0.0;
        }

        if ($years <= 5) {
            return ($wage / 2) * $years;
        }

        return (($wage / 2) * 5) + ($wage * ($years - 5));
    }

    private function resignationMultiplier(TerminationReason $reason, float $years): float
    {
        if ($reason !== TerminationReason::Resignation) {
            return 1.0;
        }

        return match (true) {
            $years < 2  => 0.0,
            $years < 5  => 1 / 3,
            $years < 10 => 2 / 3,
            default     => 1.0,
        };
    }

    private function buildBreakdown(float $wage, float $years, float $multiplier): array
    {
        if ($years <= 0) {
            return [];
        }

        $lines = [];

        if ($years <= 5) {
            $lines[] = [
                'label'  => sprintf('%.4f yrs × (%.2f ÷ 2)', $years, $wage),
                'amount' => round(($wage / 2) * $years, 2),
            ];
        } else {
            $lines[] = [
                'label'  => sprintf('5 yrs × (%.2f ÷ 2)  [first 5]', $wage),
                'amount' => round(($wage / 2) * 5, 2),
            ];
            $lines[] = [
                'label'  => sprintf('%.4f yrs × %.2f  [beyond 5]', $years - 5, $wage),
                'amount' => round($wage * ($years - 5), 2),
            ];
        }

        if ($multiplier < 1.0) {
            $lines[] = [
                'label'  => sprintf('Resignation multiplier × %s', $this->fractionLabel($multiplier)),
                'amount' => null,
            ];
        }

        return $lines;
    }

    private function fractionLabel(float $m): string
    {
        return match (true) {
            $m === 0.0             => '0',
            abs($m - 1 / 3) < 0.001 => '1/3',
            abs($m - 2 / 3) < 0.001 => '2/3',
            default                => (string) $m,
        };
    }
}
