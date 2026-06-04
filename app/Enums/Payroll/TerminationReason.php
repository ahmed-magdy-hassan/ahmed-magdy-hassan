<?php

declare(strict_types=1);

namespace App\Enums\Payroll;

enum TerminationReason: string
{
    case EmployerTermination = 'employer_termination';
    case MutualConsent       = 'mutual_consent';
    case Resignation         = 'resignation';
    case ContractExpiry      = 'contract_expiry';
    case Retirement          = 'retirement';

    public function label(): string
    {
        return match ($this) {
            self::EmployerTermination => 'Termination by Employer',
            self::MutualConsent       => 'Mutual Consent',
            self::Resignation         => 'Resignation',
            self::ContractExpiry      => 'Contract Expiry',
            self::Retirement          => 'Retirement',
        };
    }

    /** Returns true for cases that receive full EOSB entitlement (Art. 84). */
    public function isFullEntitlement(): bool
    {
        return $this !== self::Resignation;
    }
}
