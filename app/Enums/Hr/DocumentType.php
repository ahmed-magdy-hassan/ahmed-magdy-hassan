<?php

declare(strict_types=1);

namespace App\Enums\Hr;

enum DocumentType: string
{
    case Iqama       = 'iqama';
    case Passport    = 'passport';
    case WorkVisa    = 'work_visa';
    case WorkPermit  = 'work_permit';
    case Contract    = 'contract';

    public function label(): string
    {
        return match ($this) {
            self::Iqama      => 'Iqama / Residency',
            self::Passport   => 'Passport',
            self::WorkVisa   => 'Work Visa',
            self::WorkPermit => 'Work Permit',
            self::Contract   => 'Employment Contract',
        };
    }

    /** Column name on the employees table. */
    public function expiryField(): string
    {
        return match ($this) {
            self::Iqama      => 'iqama_expiry_date',
            self::Passport   => 'passport_expiry_date',
            self::WorkVisa   => 'work_visa_expiry_date',
            self::WorkPermit => 'work_permit_expiry_date',
            self::Contract   => 'contract_end_date',
        };
    }
}
