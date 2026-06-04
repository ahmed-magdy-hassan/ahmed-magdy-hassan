<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Enums\Payroll\TerminationReason;

final readonly class EosbResult
{
    public function __construct(
        public float $monthlyWage,
        public float $serviceYears,
        public TerminationReason $terminationReason,
        public float $fullEntitlement,
        public float $resignationMultiplier,
        public float $finalAmount,
        public array $breakdown,
    ) {}

    public function toArray(): array
    {
        return [
            'monthly_wage'           => $this->monthlyWage,
            'service_years'          => round($this->serviceYears, 4),
            'termination_reason'     => $this->terminationReason->value,
            'termination_reason_label' => $this->terminationReason->label(),
            'full_entitlement'       => $this->fullEntitlement,
            'resignation_multiplier' => $this->resignationMultiplier,
            'final_amount'           => $this->finalAmount,
            'breakdown'              => $this->breakdown,
        ];
    }
}
